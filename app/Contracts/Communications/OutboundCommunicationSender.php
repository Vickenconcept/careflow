<?php

namespace App\Contracts\Communications;

use App\Models\CaseCommunication;

interface OutboundCommunicationSender
{
    /**
     * Dispatch an approved outbound communication through the configured channel.
     *
     * @return array{provider: string, reference: string, channel: string}
     */
    public function send(CaseCommunication $communication): array;
}
