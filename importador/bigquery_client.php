<?php

class BigQueryClient {
    private $projectId = 'buscacnpj-490113';
    private $keyFilePath;
    private $accessToken = null;
    private $tokenExpires = 0;

    public function __construct($keyFilePath) {
        $this->keyFilePath = $keyFilePath;
    }

    private function getAccessToken() {
        if ($this->accessToken && time() < $this->tokenExpires - 60) {
            return $this->accessToken;
        }

        if (!file_exists($this->keyFilePath)) {
            throw new Exception("Google Service Account Key file not found: " . $this->keyFilePath);
        }

        $keyData = json_decode(file_get_contents($this->keyFilePath), true);
        if (!$keyData) {
            throw new Exception("Invalid JSON in Service Account Key file.");
        }

        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $now = time();
        $payload = base64_encode(json_encode([
            'iss' => $keyData['client_email'],
            'scope' => 'https://www.googleapis.com/auth/bigquery',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ]));

        $signatureSource = $header . "." . $payload;
        openssl_sign($signatureSource, $signature, $keyData['private_key'], "SHA256");
        $jwt = $signatureSource . "." . base64_encode($signature);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['access_token'])) {
            $this->accessToken = $data['access_token'];
            $this->tokenExpires = $now + $data['expires_in'];
            return $this->accessToken;
        } else {
            throw new Exception("Failed to get BigQuery access token: " . $response);
        }
    }

    public function query($sql, $pageToken = null) {
        $token = $this->getAccessToken();
        
        $url = "https://bigquery.googleapis.com/bigquery/v2/projects/{$this->projectId}/queries";
        
        $body = [
            'query' => $sql,
            'useLegacySql' => false,
            'maxResults' => 5000, // Batch size
        ];

        if ($pageToken) {
            // Note: For continuous paging, subsequent calls should use the job reference if the query is long
            // But for simple "query" endpoint, maxResults works with pageToken if provided by a previous response.
            // Actually, for query results we often need the job ID after the first call.
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("BigQuery Query API Error ($httpCode): " . $response);
        }

        $data = json_decode($response, true);
        
        // Se a query demorar, precisamos esperar ela completar
        while (isset($data['jobComplete']) && $data['jobComplete'] === false) {
            sleep(1);
            $jobId = $data['jobReference']['jobId'];
            $data = $this->getQueryResults($jobId);
        }

        return $data;
    }

    public function getQueryResults($jobId, $pageToken = null) {
        $token = $this->getAccessToken();
        $url = "https://bigquery.googleapis.com/bigquery/v2/projects/{$this->projectId}/queries/{$jobId}";
        
        $params = ['maxResults' => 5000];
        if ($pageToken) $params['pageToken'] = $pageToken;
        
        $url .= '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
    
    // Helper to parse BigQuery rows into associative arrays
    public function parseRows($response) {
        if (!isset($response['rows'])) return [];
        
        $fields = $response['schema']['fields'];
        $data = [];
        
        foreach ($response['rows'] as $row) {
            $item = [];
            foreach ($row['f'] as $index => $fValue) {
                $fieldName = $fields[$index]['name'];
                $item[$fieldName] = $fValue['v'];
            }
            $data[] = $item;
        }
        
        return $data;
    }
}
