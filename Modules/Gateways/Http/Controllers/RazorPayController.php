<?php

namespace Modules\Gateways\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\Gateways\Traits\Processor;
use Razorpay\Api\Api;

class RazorPayController extends Controller
{
    use Processor;

    private PaymentRequest $payment;
    private User $user;

    public function __construct(PaymentRequest $payment, User $user)
    {
        $config = $this->paymentConfig('razor_pay', PAYMENT_CONFIG);
        $razor = null;
        if (!is_null($config) && $config->mode == 'live') {
            $razor = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $razor = json_decode($config->test_values);
        }

        if ($razor) {
            Config::set('razor_config', [
                'api_key' => $razor->api_key,
                'api_secret' => $razor->api_secret,
            ]);
        } else {
            $envKey = env('RAZORPAY_KEY_ID');
            $envSecret = env('RAZORPAY_KEY_SECRET');
            if ($envKey && $envSecret) {
                Config::set('razor_config', [
                    'api_key' => $envKey,
                    'api_secret' => $envSecret,
                ]);
            }
        }

        $this->payment = $payment;
        $this->user = $user;
    }

    /**
     * @OA\Post(
     *   path="/api/v1/driver/payments/razorpay/create-order",
     *   tags={"Payments"},
     *   summary="Create Razorpay order for driver fare with unique receipt",
     *   security={{"sanctum":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(required={"driver_id","amount"},
     *       @OA\Property(property="driver_id", type="integer"),
     *       @OA\Property(property="amount", type="number", format="float", example=100.0),
     *       @OA\Property(property="currency", type="string", example="INR"),
     *       @OA\Property(property="meta", type="object")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function createDriverFareOrder(Request $request): JsonResponse
    {
        $request->validate([
            'driver_id' => 'required|integer',
            'amount' => 'required|numeric',
            'currency' => 'nullable|string',
            'payment_request_id' => 'nullable|string'
        ]);

        $api = new Api(config('razor_config.api_key'), config('razor_config.api_secret'));
        $currency = $request->input('currency', 'INR');
        $receipt = 'DRV-PMT-' . Str::ulid() . '-' . $request->driver_id;

        $order = $api->order->create([
            'receipt' => $receipt,
            'amount' => (int)round($request->amount * 100),
            'currency' => $currency,
            'payment_capture' => 1,
            'notes' => [
                'driver_id' => (string)$request->driver_id,
                // optional mapping to PaymentRequest for webhook reconciliation
                'payment_request_id' => $request->input('payment_request_id')
            ]
        ]);

        return response()->json([
            'order_id' => $order['id'] ?? null,
            'receipt' => $receipt,
            'amount' => $order['amount'] ?? null,
            'currency' => $order['currency'] ?? $currency,
            'key_id' => config('razor_config.api_key'),
        ]);
    }

    /**
     * @OA\Post(
     *   path="/api/v1/driver/payments/razorpay/verify",
     *   tags={"Payments"},
     *   summary="Verify Razorpay payment for driver fare",
     *   security={{"sanctum":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(required={"order_id","payment_id","signature"},
     *       @OA\Property(property="order_id", type="string"),
     *       @OA\Property(property="payment_id", type="string"),
     *       @OA\Property(property="signature", type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function verifyDriverFare(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|string',
            'payment_id' => 'required|string',
            'signature' => 'required|string',
        ]);

        $api = new Api(config('razor_config.api_key'), config('razor_config.api_secret'));
        $api->utility->verifyPaymentSignature([
            'razorpay_order_id' => $request->order_id,
            'razorpay_payment_id' => $request->payment_id,
            'razorpay_signature' => $request->signature,
        ]);

        $payment = $api->payment->fetch($request->payment_id);
        return response()->json([
            'verified' => ($payment && ($payment['status'] ?? null) === 'captured'),
            'status' => $payment['status'] ?? null,
        ]);
    }

    /**
     * @OA\Post(
     *   path="/api/webhooks/razorpay",
     *   tags={"Payments"},
     *   summary="Razorpay webhook receiver",
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="event", type="string"),
     *       @OA\Property(property="payload", type="object")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Received")
     * )
     */
    public function webhookRazorpay(Request $request): JsonResponse
    {
        $secret = env('RAZORPAY_WEBHOOK_SECRET');
        if (!$secret) {
            return response()->json(['error' => 'Webhook secret not configured'], 500);
        }

        $signature = $request->header('X-Razorpay-Signature');
        $payload = $request->getContent();

        try {
            // Verify signature
            (new Api(config('razor_config.api_key'), config('razor_config.api_secret')))
                ->utility->verifyWebhookSignature($payload, $signature, $secret);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = $request->input('event');

        // Basic handlers: update PaymentRequest where possible
        $data = $request->all();
        $paymentEntity = $data['payload']['payment']['entity'] ?? null;
        if (is_array($paymentEntity)) {
            $paymentId = $paymentEntity['id'] ?? null;
            $status = $paymentEntity['status'] ?? null;
            $notes = $paymentEntity['notes'] ?? [];
            $mappedPaymentRequestId = $notes['payment_request_id'] ?? null;

            try {
                if ($event === 'payment.captured') {
                    if ($mappedPaymentRequestId) {
                        $this->payment::where('id', $mappedPaymentRequestId)->update([
                            'payment_method' => 'razor_pay',
                            'is_paid' => 1,
                            'transaction_id' => $paymentId,
                        ]);
                    } else {
                        $this->payment::where('transaction_id', $paymentId)->update([
                            'payment_method' => 'razor_pay',
                            'is_paid' => 1,
                        ]);
                    }
                } elseif ($event === 'payment.failed') {
                    if ($mappedPaymentRequestId) {
                        $this->payment::where('id', $mappedPaymentRequestId)->update([
                            'payment_method' => 'razor_pay',
                            'is_paid' => 0,
                            'transaction_id' => $paymentId,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // swallow errors to keep webhook 200, or change to 500 if needed
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function index(Request $request): View|Factory|JsonResponse|Application
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_400, null, $this->errorProcessor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_204), 200);
        }
        $payer = json_decode($data['payer_information']);

        if ($data['additional_data'] != null) {
            $business = json_decode($data['additional_data']);
            $business_name = $business->business_name ?? "my_business";
            $business_logo = $business->business_logo ?? url('/');
        } else {
            $business_name = "my_business";
            $business_logo = url('/');
        }

        return view('Gateways::payment.razor-pay', compact('data', 'payer', 'business_logo', 'business_name'));
    }

    public function payment(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $input = $request->all();
        $api = new Api(config('razor_config.api_key'), config('razor_config.api_secret'));
        $payment = $api->payment->fetch($input['razorpay_payment_id']);

        if (count($input) && !empty($input['razorpay_payment_id'])) {
            $response = $api->payment->fetch($input['razorpay_payment_id'])->capture(array('amount' => $payment['amount'] - $payment['fee']));
            $this->payment::where(['id' => $request['payment_id']])->update([
                'payment_method' => 'razor_pay',
                'is_paid' => 1,
                'transaction_id' => $input['razorpay_payment_id'],
            ]);
            $data = $this->payment::where(['id' => $request['payment_id']])->first();
            if (isset($data) && function_exists($data->hook)) {
                call_user_func($data->hook, $data);
            }
            return $this->paymentResponse($data, 'success');
        }
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (isset($payment_data) && function_exists($payment_data->hook)) {
            call_user_func($payment_data->hook, $payment_data);
        }
        return $this->paymentResponse($payment_data, 'fail');
    }

    public function callback(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $input = $request->all();
        $data_id= base64_decode($request?->payment_data);
        if (count($input) && !empty($input['razorpay_payment_id'])) {
            $data = $this->payment::where(['id' =>$data_id])->first();
            if (isset($data) && function_exists($data->hook)) {
                $data->payment_method=  'razor_pay';
                $data->is_paid=  1;
                $data->transaction_id= $input['razorpay_payment_id'] ;
                $data->save();
                call_user_func($data->hook, $data);
                return $this->paymentResponse($data, 'success');
            }
        }
        return redirect()->route('payment-fail');
    }

    public function cancel(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        return $this->paymentResponse($payment_data, 'fail');
    }

    public function createOrder(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $request->validate([
            'payment_amount' => 'required|numeric',
            'currency_code' => 'required|string'
        ]);

        try {
            $api = new Api(config('razor_config.api_key'), config('razor_config.api_secret'));

            $razorpayOrder = $api->order->create([
                'receipt' => 'order_' . uniqid(),
                'amount' => (int)(round($request['payment_amount'], 2) * 100),
                'currency' => $request['currency_code'],
                'payment_capture' => 1
            ]);

            return response()->json([
                'status' => true,
                'payment_request_id' => $request['payment_request_id'],
                'order_id' => $razorpayOrder['id'],
                'amount' => $razorpayOrder['amount'],
                'currency' => $razorpayOrder['currency']
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage()
            ]);
        }
    }

    public function verifyPayment(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $api = new Api(config('razor_config.api_key'), config('razor_config.api_secret'));
        // Verify payment signature
        $api->utility->verifyPaymentSignature([
            'razorpay_order_id' => $request['order_id'],
            'razorpay_payment_id' => $request['payment_id'],
            'razorpay_signature' => $request['signature']
        ]);

        // Fetch payment details using payment_id
        $payment = $api->payment->fetch($request['payment_id']);

        if ($payment && isset($payment['status']) && $payment['status'] == 'captured') {
            $this->payment::where(['id' => $request['payment_request_id']])->update([
                'payment_method' => 'razor_pay',
                'is_paid' => 1,
                'transaction_id' => $request['payment_id'],
            ]);
            $data = $this->payment::where(['id' => $request['payment_request_id']])->first();
            if (isset($data) && function_exists($data->hook)) {
                call_user_func($data->hook, $data);
            }
            return $this->paymentResponse($data, 'success');
        }
        $paymentData = $this->payment::where(['id' => $request['payment_request_id']])->first();
        if (isset($paymentData) && function_exists($paymentData->hook)) {
            call_user_func($paymentData->hook, $paymentData);
        }
        return $this->paymentResponse($paymentData, 'fail');
    }
}
