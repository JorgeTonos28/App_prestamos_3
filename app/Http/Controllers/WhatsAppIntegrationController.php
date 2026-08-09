<?php

namespace App\Http\Controllers;

use App\Services\OpenAiResponsesService;
use App\Services\WhatsAppCloudService;

class WhatsAppIntegrationController extends Controller
{
    public function test(WhatsAppCloudService $whatsApp, OpenAiResponsesService $openAi)
    {
        try {
            $meta = $whatsApp->connectionDetails();
            if (! $openAi->isConfigured()) {
                throw new \RuntimeException('OpenAI no está configurado.');
            }

            $openAi->structured(
                'Prueba de conectividad. Devuelve ok=true y no realices ninguna otra acción.',
                [['type' => 'input_text', 'text' => 'health_check']],
                'health_check',
                [
                    'type' => 'object',
                    'properties' => ['ok' => ['type' => 'boolean']],
                    'required' => ['ok'],
                    'additionalProperties' => false,
                ],
                256,
            );

            $number = $meta['display_phone_number'] ?? 'configurado';

            return redirect()->back()->with('success', "Conexiones verificadas: Meta ({$number}) y OpenAI responden correctamente.");
        } catch (\Throwable $exception) {
            return redirect()->back()->with('error', 'Falló la prueba de integración: '.$exception->getMessage());
        }
    }
}
