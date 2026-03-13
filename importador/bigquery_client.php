<?php
/**
 * Cliente simplificado para Google BigQuery REST API
 * Local: /public_html/importador/bigquery_client.php
 */

class BigQueryClient {
    private $projectId = 'buscacnpj-490113';
    private $keyFile;
    private $accessToken = null;

    public function __construct($keyFilePath) {
        if (!file_exists($keyFilePath)) {
            throw new Exception("Arquivo de chave do Google Cloud não encontrado em: $keyFilePath");
        }
        $this->keyFile = json_decode(file_get_contents($keyFilePath), true);
    }

    /**
     * Gera Token de Acesso via JWT (OAuth2 para Service Accounts)
     */
    private function getAccessToken() {
        if ($this->accessToken) return $this->accessToken;

        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $payload = json_encode([
            'iss' => $this->keyFile['client_email'],
            'scope' => 'https://www.googleapis.com/auth/bigquery.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600
        ]);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $this->keyFile['private_key'], 'SHA256');
        $base64UrlSignature = $this->base64UrlEncode($signature);

        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new Exception("Falha na autenticação Google: " . ($data['error_description'] ?? $response));
        }

        $this->accessToken = $data['access_token'];
        return $this->accessToken;
    }

    private function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Executa Query e retorna resultados paginados
     */
    public function query($sql, $pageToken = null) {
        $token = $this->getAccessToken();
        $url = "https://bigquery.googleapis.com/bigquery/v2/projects/{$this->projectId}/queries";
        
        $body = [
            'query' => $sql,
            'useLegacySql' => false,
            'maxResults' => 5000,
            'timeoutMs' => 30000
        ];
        
        if ($pageToken) {
            // Se já temos um job, usamos a API de getQueryResults
            // Para simplificar, o primeiro call cria o job e retorna os primeiros resultados.
            // Para resultados massivos, o ideal é usar jobs.getQueryResults
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        
        if ($httpCode !== 200) {
            throw new Exception("Erro BigQuery ($httpCode): " . ($data['error']['message'] ?? $response));
        }

        return $this->parseResults($data);
    }

    /**
     * Busca resultados de uma query que já foi iniciada (paginação)
     */
    public function getNextPage($jobId, $pageToken) {
        $token = $this->getAccessToken();
        $url = "https://bigquery.googleapis.com/bigquery/v2/projects/{$this->projectId}/queries/{$jobId}?pageToken={$pageToken}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return $this->parseResults(json_decode($response, true));
    }

    /**
     * Converte o formato bizarro de resposta do BigQuery em um array associativo limpo
     */
    private function parseResults($data) {
        $rows = [];
        $fields = [];
        
        if (!isset($data['schema']['fields'])) return ['rows' => [], 'jobId' => null, 'pageToken' => null];

        foreach ($data['schema']['fields'] as $f) {
            $fields[] = $f['name'];
        }

        if (isset($data['rows'])) {
            foreach ($data['rows'] as $row) {
                $item = [];
                foreach ($row['f'] as $index => $val) {
                    $item[$fields[$index]] = $val['v'];
                }
                $rows[] = $item;
            }
        }

        return [
            'rows' => $rows,
            'jobId' => $data['jobReference']['jobId'] ?? null,
            'pageToken' => $data['pageToken'] ?? null,
            'totalRows' => $data['totalRows'] ?? 0
        ];
    }
}
