<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\JsonResponse;

use OpenAI;
use App\Models\Parameter;

class ChatBotController extends Controller
{
    public $messages = [];

    public function sendMessage($body, $recipient): JsonResponse
    {
        try {
            $token = Parameter::getValue('WHATSAPP_ACCESS_TOKEN');
            $phone_id = Parameter::getValue('WHATSAPP_PHONE_ID');
            $version = 'v22.0';
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'text',
                "recipient_type" => "individual",
                'text' => [
                    "body" => $body
                ],
            ];

            $message = Http::withToken($token)->post('https://graph.facebook.com/'.$version.'/'.$phone_id.'/messages', $payload)
                ->throw()->json();


        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $message,
        ], 200);
    }

    public function verifyWebhook(Request $request)
    {
        try {
            $token = Parameter::getValue('WHATSAPP_VERIFY_TOKEN');
            $query = $request->query();

            $mode = $query["hub_mode"];
            $tokenResponse = $query["hub_verify_token"];
            $challenge = $query["hub_challenge"];

            if($mode && $tokenResponse) {
                if($mode == "subscribe" && $tokenResponse == $token) {
                    return response($challenge, 200)->header('Content-Type', 'text/plain');
                }
            }else
            {
                throw new Exception("Petición invalida");
            }

        } catch (Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'success'
        ], 200);
    }

    public function proccessWebhook(Request $request)
    {
        try {
            $bodyContent = json_decode($request->getContent(), true);
            $body = '';
            $phoneUser = '';
            $myPhoneId = Parameter::getValue('WHATSAPP_PHONE_ID');

            Log::info('Webhook recibido:', $bodyContent); // Log para depuración

            $value = Arr::get($bodyContent, 'entry.0.changes.0.value');

            // Verificar si hay mensajes
            if (!empty($value["messages"])) {
                $message = $value["messages"][0];
                $phoneUser = Arr::get($message, 'from');
                $type = Arr::get($message, 'type');

                $phone1 = substr($phoneUser, 3);

                // Ignorar mis propios mensajes y otros tipos de mensajes
                if ($phoneUser !== $myPhoneId && $type === 'text') {
                    $body = Arr::get($message, 'text.body');

                    Log::info('Mensaje del usuario:', ['phone' => $phoneUser, 'body' => $body]);

                    $responseAI = $this->consultAI($body);
                    Log::info('Mensaje de Chatbot IA ATDEV:', ['ia' => 'atdev', 'body' => $responseAI]);
                    $this->sendMessage($responseAI, '52'.$phone1); // Pasar el número del usuario
                } else {
                    Log::info('Mensaje ignorado:', ['phone' => $phoneUser, 'type' => $type]);
                }
            } else {
                Log::info('No se encontraron mensajes en el webhook.');
            }

            return response()->json([
                'success' => true,
                'data' => $phoneUser,
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al procesar el webhook:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function consultAI($textUser)
    {
        try {
            $client = OpenAI::client(Parameter::getValue('OPENAI_API_KEY'));

            $prompt = "Actúa como un asesor de ventas profesional de la empresa Echopoint. Da un mensaje inicial que muestre los siguientes servicios disponibles:

                    - Desarrollo Web.
                    - Gestión de Redes Sociales.
                    - Producción de Podcast.
                    - Diseño de Contenido Digital.
                    - Desarrollo de Aplicaciones.

                    Una vez mostrado esto, cuando el usuario pregunte por el precio o la descripción de un producto o servicio, responde solamente con una consulta Eloquent de Laravel que use el modelo `Product`. Si el usuario pregunta por el precio, responde así:
                    App\Models\Product::where('name', 'like', '%Nombre del Producto%')->select('price')->first();

                    Si el usuario pregunta por la descripción, responde así:
                    App\Models\Product::where('name', 'like', '%Nombre del Producto%')->select('description')->first();

                    No expliques nada, no agregues texto adicional, solo responde con la consulta directa.

                    También puedes responder si el usuario pregunta por productos que cuesten menos de cierta cantidad. En ese caso, responde únicamente con una consulta Eloquent así:

                    App\Models\Product::where('price', '<', 1000)->get(['name', 'price']);

                    Si el usuario pregunta por un monto diferente (por ejemplo, menos de 500), reemplaza el número en la consulta. No agregues texto adicional, solo responde con la consulta.

                    Si el usuario pregunta por el total de varios productos, responde con una consulta Eloquent como esta:

                    App\Models\Product::whereIn('name', ['Producto 1', 'Producto 2'])->sum('price');

                    Sustituye los nombres por los que mencione el usuario. No agregues explicaciones ni texto adicional, solo la consulta.";

            $openaiMessages = [
                ['role' => 'system', 'content' => $prompt]
            ];

            $openaiMessages[] = [
                'role' => 'user',
                'content' => $textUser
            ];

            $response = $client->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => $openaiMessages,
                'max_tokens' => 100
            ]);

            $result = $response['choices'][0]['message']['content'];

            if (Str::startsWith($result, 'App\Models\Product::')) {
                $result = eval("return $result;");

                if (is_object($result)) {
                    if ($result instanceof \Illuminate\Support\Collection) {
                        if ($result->isEmpty()) {
                            $result = 'No se encontraron productos con ese criterio.';
                        } else {
                            $result = $result->map(function ($item) {
                                return $item->name . ': ' . $item->price;
                            })->implode("\n");
                        }
                    } elseif ($result instanceof \Illuminate\Database\Eloquent\Model) {
                        $attributes = $result->getAttributes();
                        $result = reset($attributes); // Por ejemplo: 3500
                    } else {
                        $result = 'El resultado no es un modelo ni una colección válida.';
                    }
                }
            }

            Log::info('mensaje del bot IA -> '.$result);

        }catch (\Exception $e)
        {
            return $e->getMessage();
        }

        return $result;
    }

}
