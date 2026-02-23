<?php

namespace App\Livewire;

use App\Events\MessageSent;
use App\Models\ChatMessage;
use App\Models\User;
use Livewire\Component;

class Chat extends Component
{
    public $users;
    public $selectedUser;
    public $newMessage;
    public $messages;
    public $loginId;

    public function mount()
    {
        $this->users = User::whereNot("id", auth()->id())
            ->latest()
            ->get();
        $this->selectedUser = $this->users->first();
        $this->loadMessages();
        $this->loginId = auth()->id();
    }

    public function selectUser($userId)
    {
        $this->selectedUser = User::find($userId);
        $this->loadMessages();
    }

    public function loadMessages()
    {
        $this->messages = ChatMessage::query()
            ->where(function ($q) {
                $q->where("sender_id", auth()->id())->where(
                    "receiver_id",
                    $this->selectedUser->id,
                );
            })
            ->orWhere(function ($q) {
                $q->where("sender_id", $this->selectedUser->id)->where(
                    "receiver_id",
                    auth()->id(),
                );
            })
            ->oldest()
            ->get();
    }

    public function submit()
    {
        if (!$this->newMessage) {
            return;
        }

        $message = ChatMessage::create([
            "sender_id" => auth()->id(),
            "receiver_id" => $this->selectedUser->id,
            "message" => $this->newMessage,
        ]);

        $this->messages->push($message);
        $this->newMessage = "";

        broadcast(new MessageSent($message));
    }

    public function updatedNewMessage($value)
    {
        $this->dispatch(
            "userTyping",
            userID: $this->loginId,
            userName: auth()->id(),
            selectedUserID: $this->selectedUser->id,
        );
    }

    public function getListeners()
    {
        return [
            "echo-private:chat.{$this->loginId},MessageSent" => "newChatMessageNotification",
        ];
    }

    public function newChatMessageNotification($message)
    {
        if ($message["sender_id"] == $this->selectedUser->id) {
            $messageObj = ChatMessage::find($message["id"]);
            $this->messages->push($messageObj);
        }
    }

    public function render()
    {
        return view("livewire.chat");
    }
}
