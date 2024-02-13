<?php

namespace App\Rules\ManageTransactionRules;

use App\Models\BorrowedItem;
use App\Models\BorrowedItemStatus;
use App\Utils\Constants\Statuses\BORROWED_ITEM_STATUS;
use Illuminate\Contracts\Validation\Rule;


class IsItemInPossessionOrUnreturned implements Rule
{
    private $inpossessionStatusId;
    private $unreturnedStatusId;

    private $request;

    public function __construct($request)
    {
        $this->request = $request;
        $this->inpossessionStatusId = BorrowedItemStatus::getIdByStatus(BORROWED_ITEM_STATUS::IN_POSSESSION);
        $this->unreturnedStatusId = BorrowedItemStatus::getIdByStatus(BORROWED_ITEM_STATUS::UNRETURNED);
    }

    public function passes($attribute, $value)
    {

        $borrowedItem = BorrowedItem::find($value);

        if (!$borrowedItem) {
            return false;
        }

        return $borrowedItem->borrowed_item_status_id === $this->inpossessionStatusId
            || $borrowedItem->borrowed_item_status_id === $this->unreturnedStatusId;

    }

    public function message()
    {
        return "Request object has invalid field";
    }

}