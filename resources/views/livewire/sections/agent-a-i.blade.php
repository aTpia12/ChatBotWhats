<div class="flex flex-col h-3/4 md:h-5/6 lg:h-3/4 w-full max-w-3xl mx-auto shadow-lg rounded-xl overflow-hidden bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
    <div class="bg-gray-100 dark:bg-gray-900 py-4 px-6 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Chatbot IA</h2>
    </div>

    <div class="flex-1 overflow-y-auto p-4 space-y-2" id="messages-container-large">
        {{-- Contenedor de mensajes --}}
        @foreach ($messages as $message)
            <div class="flex {{ $message['sender'] === 'user' ? 'justify-end' : 'justify-start' }} mb-2">
                <div class="{{ $message['sender'] === 'user' ? 'bg-blue-500 text-white rounded-lg rounded-tl-lg' : 'bg-gray-300 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg rounded-tr-lg' }} py-2 px-4 max-w-3/4">
                    <p class="text-sm">{{ $message['text'] }}</p>
                    <span class="text-xs text-black dark:text-black">{{ $message['time']->format('h:i A') }}</span>
                </div>
            </div>
        @endforeach

        {{-- Indicador de "escribiendo..." --}}
        <div wire:loading.flex wire:target="userMessage" class="justify-start mb-2">
            <div class="bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-300 rounded-lg py-2 px-4 max-w-xs">
                    <p class="text-sm animate-pulse">Escribiendo...</p>
            </div>
        </div>
    </div>

    <div class="bg-gray-100 dark:bg-gray-900 py-4 px-6 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center">
            <textarea
                wire:model="userMessage"
                placeholder="Escribe tu mensaje..."
                rows="3" {{-- Aumenta el número de filas --}}
                class="flex-1 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-300 resize-none" {{-- resize-none para evitar que el usuario lo redimensione --}}
            ></textarea>
            <button
                wire:click="sendMessage"
                class="ml-3 bg-blue-700 hover:bg-blue-800 text-white font-semibold py-2 px-4 rounded-md shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 flex items-center" {{-- Añadimos flex items-center para alinear el icono y el texto --}}
                wire:loading.attr="disabled"
            >
                <svg wire:loading.remove wire:target="sendMessage" class="-ml-1 mr-2 h-5 w-5 inline-block" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 00-1 1v2a1 1 0 001 1h3.586l-2.293 2.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd" />
                </svg>
                <svg wire:loading wire:target="sendMessage" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white inline-block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m15.357 2h.008v5h-.582M9 11a3 3 0 013-3m-3 6a3 3 0 003-3m-3-3a3 3 0 003 3zm6 6a3 3 0 01-3-3m3 6a3 3 0 00-3-3m3-3a3 3 0 00-3 3z" />
                </svg>
                Enviar
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('livewire:load', function () {
        const messagesContainer = document.getElementById('messages-container-large');
        Livewire.hook('message.processed', (message, component) => {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        });
    });
</script>
