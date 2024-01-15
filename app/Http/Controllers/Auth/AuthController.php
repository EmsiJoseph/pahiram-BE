<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\ApcisToken;
use App\Models\Course;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Utils\NewUserDefaultData;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Login Method
     */
    public function login(LoginRequest $request)
    {
        $validatedData = $request->validated();
        try {
            /**
             * 2. Access APCIS login API
             */
            $response = Http::timeout(10)->post('http://167.172.74.157/api/login', $validatedData);
            $apiReturnData = json_decode($response->body(), true);

            // APCIS login API returns false
            if ($apiReturnData['status'] == false) {
                return response($apiReturnData, 401);
            }


            $apiUserData = $apiReturnData['data']['user'];
            $apiCourseData = $apiReturnData['data']['course'];
            $apiTokenData = $apiReturnData['data']['apcis_token'];

            /**
             * 3. Check COURSE if already exist in pahiram-BE Database
             */
            $course = Course::where('course_acronym', $apiCourseData['course_acronym'])->first();
            // Does not exist yet, add to db, else do nothing
            if (!$course) {
                $course = Course::create($apiCourseData);
            }

            /**
             * 4. Check USER if already exist in pahiram-BE Database
             */
            $user = User::where('apc_id', $apiUserData['apc_id'])->first();

            // Does not exist yet, add user to db
            if (!$user) {
                $defaultData = NewUserDefaultData::defaultData($course);
                $newUser = array_merge($apiUserData, $defaultData);
                $user = User::create($newUser);
            }

            /**
             * 5. Generate Pahiram Token with expiration
             */
            $expiresAt = \DateTime::createFromFormat('Y-m-d H:i:s', $apiTokenData['expires_at']);
            $pahiramToken = $user->createToken('Pahiram-Token', ['*'], $expiresAt)->plainTextToken;

            /**
             * 6. Store APCIS token to Pahiram DB
             */
            $newToken = [
                'user_id' => $user->id,
                'token' => $apiTokenData['access_token'],
                'expires_at' => $apiTokenData['expires_at']
            ];

            $apcisToken = ApcisToken::create($newToken);

            // Success return values 
            // make dept_id as code, role also,
            $role = Role::where('id', $user->user_role_id)->firstOrFail()->role;

            $department = null;
            if ($user->department_id !== null) {
                $department = Department::where('id', $user->department_id)->firstOrFail()->department_acronym;
            }

            unset($user['department_id']);
            unset($user['user_role_id']);

            return response([
                'status' => true,
                'data' => [
                    'user' => [
                        ...$user->toArray(),
                        'department_code' => $department,
                        'role' => $role
                    ],
                    'pahiram_token' => $pahiramToken,
                    'apcis_token' => $apcisToken['token'],
                ],
                'method' => 'POST'
            ], 200);

        } catch (RequestException $exception) {
            // Handle HTTP request exception
            \Log::error('API Request Failed:', ['exception' => $exception->getMessage()]);

            return response([
                'status' => false,
                'error' => 'APCIS API login request failed',
                'method' => 'POST'
            ], 500);
        } catch (\Exception $exception) {
            // Handle other exceptions
            \Log::error('Unexpected Exception:', ['exception' => $exception->getMessage()]);
            return response([
                'status' => false,
                'error' => 'Unexpected error',
                'method' => 'POST'
            ], 500);
        }

    }

    /**
     * Logout current session.
     */
    public function logout(Request $request)
    {
        $currentToken = $request->user()->currentAccessToken();
        $currentToken->delete();

        return response([
            'status' => true,
            'message' => 'Logged out',
            'method' => 'DELETE'
        ], 200);
    }

    /**
     * Logout all devices.
     */
    public function logoutAllDevices(Request $request)
    {
        $allTokens = $request->user()->tokens();
        $allTokens->delete();
        ApcisToken::where('user_id', $request->user()->id)->delete();

        return response([
            'status' => true,
            'message' => 'Logged out from all devices',
            'method' => 'DELETE'
        ], 200);
    }
}
