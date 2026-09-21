<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    private string $databaseUrl;

    public function __construct()
    {
        $this->databaseUrl = rtrim(config('firebase.database_url'), '/');
    }

    /**
     * Write an activity node to /clubs/{clubId}/events/{eventId}.
     * TODO: rename Firebase node path to /clubs/{clubId}/activities/ once the Supabase migration lands.
     */
    public function writeEvent(int $clubId, int $eventId, array $data): void
    {
        $this->put("/clubs/{$clubId}/events/{$eventId}", $data);
    }

    /**
     * Write an announcement (post) node to /clubs/{clubId}/posts/{postId}.
     */
    public function writePost(int $clubId, int $postId, array $data): void
    {
        $this->put("/clubs/{$clubId}/posts/{$postId}", $data);
    }

    /**
     * Write a member registration status node to /clubs/{clubId}/member_status/{memberId}.
     */
    public function writeMemberStatus(int $clubId, int $memberId, array $data): void
    {
        $this->put("/clubs/{$clubId}/member_status/{$memberId}", $data);
    }

    /**
     * Write a notification node to /notifications/{userUid}/{notifId}.
     */
    public function writeNotification(string $userUid, int $notifId, array $data): void
    {
        $this->put("/notifications/{$userUid}/{$notifId}", $data);
    }

    /**
     * Write an AI queue item to /ai_queue/{queueId}.
     */
    public function writeAiQueueItem(int $queueId, array $data): void
    {
        $this->put("/ai_queue/{$queueId}", $data);
    }

    /**
     * Write a violation node to /clubs/{clubId}/violations/{violationId}.
     */
    public function writeViolation(int $clubId, int $violationId, array $data): void
    {
        $this->put("/clubs/{$clubId}/violations/{$violationId}", $data);
    }

    /**
     * Delete a node at the given path.
     */
    public function delete(string $path): void
    {
        $idToken = session('firebase_id_token');
        $url     = $this->databaseUrl . $path . '.json';

        if ($idToken) {
            $url .= '?auth=' . $idToken;
        }

        Http::delete($url);
    }

    /**
     * PUT data to the given RTDB path, authenticated with the session ID token.
     */
    private function put(string $path, array $data): void
    {
        $idToken = session('firebase_id_token');
        $url     = $this->databaseUrl . $path . '.json';

        if ($idToken) {
            $url .= '?auth=' . $idToken;
        }

        $response = Http::put($url, $data);

        if ($response->failed()) {
            throw new \RuntimeException(
                'Firebase RTDB write failed for ' . $path . ': ' . $response->body()
            );
        }
    }
}
