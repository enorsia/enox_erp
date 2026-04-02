<?php

namespace App\Http\Controllers;

use App\Events\PayloadRelayed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PayloadRelayController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->all();

        $eventName = 'enoxsuite.payload.received';
        $channel = 'enoxsuite.contact-realtime';

        Log::info("Received payload for event '{$eventName}' on channel '{$channel}': " . json_encode($data));

        event(new PayloadRelayed($eventName, $channel, $data));

        return response()->json([
            'message' => 'Payload received and broadcasted.',
            'data' => $data,
            'event' => $eventName,
            'channel' => $channel,
        ], Response::HTTP_ACCEPTED);
    }
}

