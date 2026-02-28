<?php

namespace HabibTalib\SecurePay\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use HabibTalib\SecurePay\Events\PaymentSuccessful;
use HabibTalib\SecurePay\Events\PaymentFailed;
use HabibTalib\SecurePay\Facades\SecurePay;

class SecurePayCallbackController extends Controller
{
    /**
     * Handle SecurePay callback (POST from SecurePay server).
     */
    public function callback(Request $request)
    {
        $payload = $request->all();

        // Verify HMAC signature
        if (!SecurePay::verifyCallback($payload)) {
            logger()->warning('SecurePay callback: Invalid signature', $payload);
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payment = SecurePay::parseCallback($payload);

        if ($payment['status'] === 'successful') {
            event(new PaymentSuccessful($payment, $payload));
        } else {
            event(new PaymentFailed($payment, $payload));
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle customer redirect after payment.
     */
    public function redirect(Request $request)
    {
        $payload = $request->all();
        $payment = SecurePay::parseCallback($payload);

        if ($payment['status'] === 'successful') {
            return redirect()->route('securepay.success', $payment);
        }

        return redirect()->route('securepay.failed', $payment);
    }
}
