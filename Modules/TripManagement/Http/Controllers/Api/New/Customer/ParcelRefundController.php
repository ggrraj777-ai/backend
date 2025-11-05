<?php

namespace Modules\TripManagement\Http\Controllers\Api\New\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Service\Interface\ParcelRefundServiceInterface;

class ParcelRefundController extends Controller
{
    protected $parcelRefundService;

    public function __construct(ParcelRefundServiceInterface $parcelRefundService)
    {
        $this->parcelRefundService = $parcelRefundService;
    }

    public function createParcelRefundRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'reason' => 'nullable|max:255',
            'parcel_approximate_price' => 'required|numeric',
            'attachments' => 'required',
            'customer_note' => 'nullable|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $parcelRefund = $this->parcelRefundService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id]);
        if ($parcelRefund) {
            return response()->json(responseFormatter(PARCEL_REFUND_ALREADY_EXIST_200), 403);
        }
        $parcelRefund = $this->parcelRefundService->create(data: $request->all());
        if ($parcelRefund?->tripRequest?->driver?->fcm_token) {
            try {
                $push = getNotification('parcel_amount_deducted');
                sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->driver?->fcm_token,
                    title: translate($push['title']),
                    description: translate(textVariableDataFormat(value: $push['description'],parcelId: $parcelRefund?->tripRequest?->ref_id, approximateAmount:  set_currency_symbol($parcelRefund->parcel_approximate_price))) ,
                    status: $push['status'],
                    ride_request_id: $parcelRefund?->trip_request_id,
                    type: $parcelRefund?->trip_request_id,
                    action: $push['action'],
                    user_id: $parcelRefund?->tripRequest?->driver?->id
                );
            } catch (\Exception $exception) {

            }
        }

        return response()->json(responseFormatter(PARCEL_REFUND_CREATE_200), 200);
    }
}
