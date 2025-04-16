<?php

namespace App\Livewire\Sections;

use Illuminate\Support\Str;
use Livewire\Component;
use OpenAI;
use App\Models\Product;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Parameter;
use App\Models\Log;

class AgentAI extends Component
{
    public $userMessage;
    public $noMessage = false;
    public $messages = []; // Asegúrate de tener esta propiedad para los mensajes

    public function sendMessage()
    {
        if (empty(trim($this->userMessage))) {
            $this->noMessage = true;
            // Puedes agregar un timeout para que el mensaje desaparezca después de un tiempo
            $this->messages[] = ['sender' => 'chatbot', 'text' => 'Respuesta del bot: Por favor escribe una pregunta!', 'time' => now()];
            return;
        }

        try {

            $client = OpenAI::client(Parameter::getValue('OPENAI_API_KEY'));

            $chat = Chat::firstOrCreate(
                ['whatsapp_number' => '522285211115'],
                ['name' => 'Augusto']
            );

            ChatMessage::create([
                'chat_id' => $chat->id,
                'message' => $this->userMessage,
                'sender' => 'user',
            ]);

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

                    Si el usuario pregunta por el total del precio de varios productos, responde con una consulta Eloquent como esta:

                    App\Models\Product::whereIn('name', ['Producto 1', 'Producto 2'])->sum('price');

                    Sustituye los nombres por los que mencione el usuario. No agregues explicaciones ni texto adicional, solo la consulta.";



            $openaiMessages = [
                ['role' => 'system', 'content' => $prompt]
            ];

            foreach ($this->messages as $message) {
                $openaiMessages[] = [
                    'role' => $message['sender'] === 'user' ? 'user' : 'assistant',
                    'content' => $message['text']
                ];
            }

            $openaiMessages[] = [
                'role' => 'user',
                'content' => $this->userMessage
            ];

            $response = $client->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => $openaiMessages,
                'max_tokens' => 300
            ]);

            $result = $response['choices'][0]['message']['content'];

            if (count($this->messages) > 0) {
                $query = trim($result);

                // Validar que parezca una consulta Eloquent
                if (Str::startsWith($query, 'App\Models\Product::')) {
                    try {
                        $evaluated = eval("return $query;");
                        if ($evaluated) {
                            // Puedes verificar si es un objeto y tomar solo los campos que necesitas
                            if (is_object($evaluated)) {
                                if ($evaluated instanceof \Illuminate\Support\Collection) {
                                    if ($evaluated->isEmpty()) {
                                        $result = 'No se encontraron productos con ese criterio.';
                                    } else {
                                        $result = $evaluated->map(function ($item) {
                                            return $item->name . ': ' . $item->price;
                                        })->implode("\n");
                                    }
                                } elseif ($evaluated instanceof \Illuminate\Database\Eloquent\Model) {
                                    $attributes = $evaluated->getAttributes();
                                    $result = reset($attributes); // Por ejemplo: 3500
                                } else {
                                    $result = 'El resultado no es un modelo ni una colección válida.';
                                }
                            } else {
                                $result = $evaluated;
                            }
                        } else {
                            $result = 'No se encontró el producto solicitado.';
                        }
                    } catch (\Throwable $e) {
                        $result = 'Error en la consulta generada.';
                    }
                } else {
                    $result = 'No se pudo interpretar la respuesta del asistente.';
                }
            }

            $this->messages[] = ['sender' => 'user', 'text' => $this->userMessage, 'time' => now()];

            $this->messages[] = ['sender' => 'chatbot', 'text' => (string) $result, 'time' => now()];

        } catch (\Exception $e)
        {
            $this->messages[] = ['sender' => 'chatbot', 'text' => 'Error '.$e->getMessage(), 'time' => now()];
        }

        $this->userMessage = '';
    }

    public function render()
    {
        return view('livewire.sections.agent-a-i');
    }
}
