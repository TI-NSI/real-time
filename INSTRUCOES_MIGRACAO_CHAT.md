# Guia de Implementação: Chat Real-time com Livewire e Reverb

Este guia descreve os passos para migrar a funcionalidade de chat em tempo real deste projeto de exemplo para um projeto Laravel existente (Blade).

---

## 1. Instalação de Dependências

No seu projeto de destino (empresa), instale os pacotes necessários.

### Backend (Composer)
Instale o Livewire e o Laravel Reverb (servidor WebSocket):

```bash
composer require livewire/livewire laravel/reverb
```

Após instalar, execute o comando de instalação do Reverb para publicar configurações:

```bash
php artisan reverb:install
```

### Frontend (NPM)
Instale as bibliotecas client-side para ouvir os eventos:

```bash
npm install --save-dev laravel-echo pusher-js
```

---

## 2. Banco de Dados

### Migration
Crie a tabela para armazenar as mensagens.
**Arquivo:** `database/migrations/xxxx_xx_xx_create_chat_messages_table.php`

```php
Schema::create('chat_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sender_id')->constrained('users');
    $table->foreignId('receiver_id')->constrained('users');
    $table->text('message');
    $table->timestamps();
});
```

Rode a migration:
```bash
php artisan migrate
```

### Model
Crie ou copie o model `ChatMessage`.
**Arquivo:** `app/Models/ChatMessage.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
```

---

## 3. Configuração do Backend (Lógica)

### Evento de Broadcast
Este evento é responsável por enviar a mensagem para o WebSocket.
**Arquivo:** `app/Events/MessageSent.php`

```php
<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(ChatMessage $message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        // Envia para o canal privado do receptor
        return [
            new PrivateChannel('chat.' . $this->message->receiver_id),
        ];
    }
}
```

### Canais de Transmissão (Autorização)
Defina quem pode ouvir o canal.
**Arquivo:** `routes/channels.php`

```php
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
```

### Componente Livewire
Copie o componente `App/Livewire/Chat.php`. Certifique-se de ajustar o namespace se necessário.

---

## 4. Configuração do Frontend (JavaScript)

No arquivo `resources/js/echo.js` (ou `bootstrap.js`), configure o Reverb. O comando `reverb:install` geralmente faz isso, mas verifique:

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

**Nota:** Lembre-se de rodar `npm run dev` ou `npm run build` para compilar os assets.

---

## 5. A View (Blade) - Atenção Importante

O projeto original usa uma biblioteca chamada **Flux UI** (`<flux:heading>`). Se o seu projeto da empresa não usa Flux, você deve substituir essas tags por HTML padrão (Bootstrap ou Tailwind).

**Arquivo:** `resources/views/livewire/chat.blade.php`

Abaixo está uma versão "limpa" (apenas Tailwind padrão) para evitar erros:

```html
<div class="flex h-[600px] border rounded shadow bg-white">
    <!-- Lista de Usuários -->
    <div class="w-1/3 border-r bg-gray-50">
        <div class="p-4 font-bold border-b">Usuários</div>
        <div class="overflow-y-auto h-full">
            @foreach ($users as $user)
                <div wire:click="selectUser({{ $user->id }})" 
                     class="p-3 cursor-pointer hover:bg-gray-200 {{ $selectedUser->id === $user->id ? 'bg-blue-100' : '' }}">
                    <p class="font-semibold">{{ $user->name }}</p>
                    <p class="text-xs text-gray-500">{{ $user->email }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Área de Chat -->
    <div class="w-2/3 flex flex-col">
        <!-- Cabeçalho -->
        <div class="p-4 border-b bg-gray-100 flex justify-between items-center">
            <span class="font-bold">{{ $selectedUser->name }}</span>
        </div>

        <!-- Mensagens -->
        <div class="flex-1 p-4 overflow-y-auto bg-gray-50 space-y-3" id="chat-container">
            @foreach ($messages as $msg)
                <div class="flex {{ $msg->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-xs px-4 py-2 rounded-lg {{ $msg->sender_id === auth()->id() ? 'bg-blue-500 text-white' : 'bg-gray-300 text-black' }}">
                        {{ $msg->message }}
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Input -->
        <div class="p-4 border-t bg-white">
            <form wire:submit="submit" class="flex gap-2">
                <input type="text" wire:model="newMessage" 
                       class="flex-1 border p-2 rounded" 
                       placeholder="Digite sua mensagem...">
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Enviar</button>
            </form>
        </div>
    </div>
</div>
```

---

## 6. Integração Final

Na sua página Blade existente onde o chat deve aparecer (ex: `resources/views/chat/index.blade.php`):

```html
@extends('layouts.app')

@section('content')
    <div class="container mx-auto py-6">
        <!-- Renderiza o componente Livewire -->
        <livewire:chat />
    </div>
@endsection
```

### Configuração do Ambiente (.env)
Certifique-se de que o `.env` do projeto da empresa tenha as chaves do Reverb (geradas no passo 1):

```env
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME="http"

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

## 7. Executando

Para testar, você precisará de 3 terminais rodando:
1. `php artisan serve` (Servidor Web)
2. `npm run dev` (Vite / Frontend)
3. `php artisan reverb:start` (Servidor WebSocket)
