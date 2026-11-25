<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MessageRequest;
use App\Http\Requests\Api\SupportTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Services\SupportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SupportService $support,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->successResponse(SupportTicketResource::collection($request->user('api')->supportTickets()->latest()->paginate(20)));
    }

    public function store(SupportTicketRequest $request): JsonResponse
    {
        $user = $request->user('api');
        $type = $request->boolean('as_vendor') && $user->isVendor() ? TicketType::VENDOR : TicketType::USER;

        $ticket = $this->support->open($user, $type, $request->string('subject')->toString(), $request->string('message')->toString());

        return $this->resourceResponse(new SupportTicketResource($ticket->load('messages')), 'We got it. We reply within 24 hours.', 201);
    }

    public function show(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $this->authorizeOwner($request, $supportTicket);

        return $this->resourceResponse(new SupportTicketResource($supportTicket->load('messages')));
    }

    public function reply(MessageRequest $request, SupportTicket $supportTicket): JsonResponse
    {
        $this->authorizeOwner($request, $supportTicket);
        $this->support->reply($supportTicket, $request->user('api'), $request->string('body')->toString(), false);

        return $this->resourceResponse(new SupportTicketResource($supportTicket->refresh()->load('messages')), 'Message sent.', 201);
    }

    private function authorizeOwner(Request $request, SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user('api')->id, 404);
    }
}
