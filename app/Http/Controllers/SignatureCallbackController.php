<?php

namespace App\Http\Controllers;

use App\Support\Signatures;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook des Unterschriften-Anbieters (#40): ohne Anmeldung und ohne Seitenpasswort, aber nur mit gültiger
 * Signatur (HMAC je Auftrag) und nur für bekannte Aufträge. Aus dem Aufruf wird nur die Dokument-ID übernommen;
 * den Status fragt das Intranet danach selbst beim Anbieter ab.
 */
class SignatureCallbackController extends Controller
{
    public function __invoke(Request $request, Signatures $signatures, string $provider): Response
    {
        $raw = (string) $request->getContent();
        if (strlen($raw) > 100_000) {
            return response('', 413);
        }

        return response('', $signatures->callback($provider, $request->headers->all(), $raw));
    }
}
