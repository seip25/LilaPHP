<?php

namespace Sockets;

/**
 * Real-Time WebSocket Logic & Authentication Handler (`backend/sockets/Handler.php`).
 * 
 * Intercepts connection events, room join attempts (e.g. password/token verification),
 * and custom incoming messages right before they are processed by `_core/Ws.php`.
 */
class Handler
{
    /**
     * Fired immediately when a new client connects to the WebSocket server (`ws://localhost:8080/ws`).
     * 
     * You can inspect IP address, headers, or assign custom attributes to `$connection`.
     */
    public static function onConnect(object $connection): void
    {
        // Example: Assign a unique string socket_id for tracking
        $connection->socket_id = 'sock_' . bin2hex(random_bytes(6));
    }

    /**
     * Fired when a client requests to join a room (`ws.join('room_name', { password: '...' })`).
     * 
     * @param object $connection Workerman connection object
     * @param string $room Name of the room being joined
     * @param array $payload Additional parameters sent during join (e.g., token or password)
     * @return bool Return `true` to allow joining the room, or `false` to reject/deny access.
     */
    public static function onJoin(object $connection, string $room, array $payload = []): bool
    {
        /*
        // EXAMPLE: Room Password Verification
        if ($room === 'admin_room') {
            $password = $payload['password'] ?? '';
            if ($password !== 'secret123') {
                $connection->send(json_encode([
                    'event' => 'error',
                    'message' => "Unauthorized access to room `{$room}`. Incorrect password."
                ]));
                return false; // Reject join
            }
        }
        */

        return true; // Allow join by default
    }

    /**
     * Fired when a client emits/broadcasts a message (`ws.emit('chat_message', { text: 'Hello' }, 'room_name')`).
     * 
     * @param object $connection Workerman connection object of the sender
     * @param string $event Event name (e.g., 'chat_message')
     * @param array $payload Data payload sent by the client
     * @param string|null $room Room target (if applicable)
     * @param object $wsWorker The main Workerman worker instance
     * @return bool Return `true` to let LilaPHP automatically broadcast the event to the room/connections.
     *              Return `false` if you handled broadcasting manually or want to block the message.
     */
    public static function onMessage(object $connection, string $event, array $payload, ?string $room, object $wsWorker): bool
    {
        /*
        // EXAMPLE: Chat profanity filter or database persistence
        if ($event === 'chat_message') {
            // 1. Save to MySQL using API models:
            // \Models\Message::create(['room' => $room, 'text' => $payload['text']]);
            
            // 2. Or modify the payload before it gets broadcasted to everyone:
            // $payload['sender_id'] = $connection->socket_id;
        }
        */

        return true; // Allow automatic broadcast
    }

    /**
     * Fired when a client disconnects from the WebSocket server.
     */
    public static function onClose(object $connection): void
    {
        // Cleanup or notify room members that user left if needed
    }
}
