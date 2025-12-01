<?php

namespace franciscoblancojn\GohighlevelApi;

class GohighlevelApi
{
    private string $token;
    private string $locationId;
    private string $version = "2021-07-28";
    private string $url = "https://services.leadconnectorhq.com";

    public function __construct(array $config)
    {
        $this->token = $config["token"];
        $this->locationId = $config["locationId"];
    }

    /* ---------------------------------------------------------
     * REQUEST (equivalente a onRequest de TS)
     * --------------------------------------------------------- */
    private function request(array $props): array
    {
        try {
            $method  = $props["method"];
            $url     = $this->url . $props["url"];
            $body    = $props["body"] ?? null;
            $ifError = $props["ifError"] ?? null;

            $ch = curl_init();

            $headers = [
                "Accept: application/json",
                "Content-Type: application/json",
                "Authorization: Bearer {$this->token}",
                "Version: {$this->version}",
            ];

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }

            $response = curl_exec($ch);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return [
                    "status"  => "error",
                    "message" => $error,
                    "error"   => $error
                ];
            }

            $json = json_decode($response, true);

            if ($ifError && is_callable($ifError)) {
                $ifError($json);
            }

            return [
                "status"  => "ok",
                "message" => "Request ok",
                "data"    => $json
            ];

        } catch (\Throwable $th) {
            return [
                "status"  => "error",
                "message" => $th->getMessage(),
                "error"   => $th
            ];
        }
    }

    /* ---------------------------------------------------------
     * CONTACT UPSERT (equivalente a TS)
     * --------------------------------------------------------- */
    public function onContactUpsert(array $props): array
    {
        $data = $props["data"];

        return $this->request([
            "url" => "/contacts/upsert",
            "method" => "POST",
            "body" => array_merge($data, [
                "locationId" => $this->locationId
            ]),
            "ifError" => function ($result) {
                // TS: if (!result?.succeded) throw
                if (!isset($result["succeded"]) || !$result["succeded"]) {
                    throw new \Exception("Error in contact upsert: " . json_encode($result));
                }
            }
        ]);
    }

    /* ---------------------------------------------------------
     * CONTACT GET (igual a TS)
     * --------------------------------------------------------- */
    public function onContactGet(array $props): array
    {
        $id = $props["id"] ?? null;

        $url = "/contacts";
        if ($id) {
            $url .= "/{$id}";
        }

        $url .= "?locationId={$this->locationId}";

        return $this->request([
            "url" => $url,
            "method" => "GET",
        ]);
    }

    /* ---------------------------------------------------------
     * CUSTOM FIELDS GET (igual a TS)
     * --------------------------------------------------------- */
    public function onCustomFieldsGet(): array
    {
        return $this->request([
            "url" => "/locations/{$this->locationId}/customFields",
            "method" => "GET"
        ]);
    }

    /* ---------------------------------------------------------
     * BUSINESS CREATE (nuevo, igual a TS)
     * --------------------------------------------------------- */
    public function onBusinessCreate(array $props): array
    {
        $data = $props["data"];

        return $this->request([
            "url" => "/businesses/",
            "method" => "POST",
            "body" => array_merge($data, [
                "locationId" => $this->locationId
            ]),
            "ifError" => function ($result) {
                if (!isset($result["business"]["id"])) {
                    throw new \Exception("Error creating business: " . json_encode($result));
                }
            }
        ]);
    }
}
