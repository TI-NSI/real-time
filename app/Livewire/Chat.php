<?php

namespace App\Livewire;

use App\Models\ChatMessage;
use App\Models\User;
use Livewire\Component;

Class Chat extends Component
{
    public $users;
    public $selectedUser;
    public $newMessage;
    public $messages;

    public function mount()
    {
        $this->users = User::whereNot('id', auth()->id())->get();
        $this->selectedUser = $this->users->first();

        $this->messages = ChatMessage::query()
        ->where(function($q) {
            $q->where('sender_id', auth()->id())
              ->where('receiver_id', $this->selectedUser->id);
        })
        ->orWhere(function($q) {
            $q->where('sender_id', $this->selectedUser->id)
              ->where('receiver_id', auth()->id());
        });
    }


    public function selectUser($userId)
    {
        $this->selectedUser = User::find($userId);
    }

    public function submit() {
        if (!$this->newMessage) return;

        ChatMessage::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $this->selectedUser->id,
            'message' => $this->newMessage
        ]);

        $this->newMessage = '';
    }

    public function render()
    {
        return view('livewire.chat');
    }
}

