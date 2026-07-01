<?php

/**
 * twenty.php
 * Integração com o Twenty CRM usando a API REST gerada pelo schema do workspace.
 * Objectos e campos customizados são configurados via variáveis de ambiente.
 */

// Enable debug logging (set to false in production)
define('TWENTY_DEBUG', false);

function logDebug($message) {
    if (TWENTY_DEBUG) {
        $logFile = __DIR__ . '/twenty_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }
}

function logTwentyError(string $context, array $result): void
{
    $logFile = __DIR__ . '/twenty_error.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = [
        'context' => $context,
        'error' => $result['error'] ?? null,
        'httpCode' => $result['httpCode'] ?? null,
        'details' => $result['details'] ?? null,
        'data' => $result['data'] ?? null,
    ];

    file_put_contents($logFile, "[{$timestamp}] " . json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
}

function isDuplicateTwentyError(array $result): bool
{
    $errorText = strtolower((string)($result['error'] ?? ''));
    if (strpos($errorText, 'duplicate') !== false) {
        return true;
    }

    $data = $result['data'] ?? null;
    if (!is_array($data)) {
        return false;
    }

    $messageSources = [];
    if (!empty($data['messages']) && is_array($data['messages'])) {
        $messageSources = array_merge($messageSources, $data['messages']);
    }
    if (!empty($data['message'])) {
        $messageSources[] = $data['message'];
    }
    if (!empty($data['error'])) {
        $messageSources[] = $data['error'];
    }

    foreach ($messageSources as $message) {
        if (is_string($message) && stripos($message, 'duplicate') !== false) {
            return true;
        }
    }

    return false;
}

$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if (empty($trimmedLine) || strpos($trimmedLine, '#') === 0) {
            continue;
        }

        if (strpos($trimmedLine, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $trimmedLine, 2);
        $name = trim($name);
        $value = trim($value);
        $value = trim($value, "\"'");

        if (!empty($name)) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

function normalizeTwentyBaseUrl(string $baseUrl): string
{
    $trimmed = trim($baseUrl);
    if ($trimmed === '') {
        return 'https://api.twenty.com';
    }

    $trimmed = rtrim($trimmed, '/');
    if (preg_match('#/rest$#', $trimmed)) {
        return $trimmed;
    }

    return $trimmed;
}

function buildTwentyGraphqlUrl(): string
{
    $baseUrl = normalizeTwentyBaseUrl($_ENV['TWENTY_BASE_URL'] ?? $_SERVER['TWENTY_BASE_URL'] ?? getenv('TWENTY_BASE_URL') ?: 'https://api.twenty.com');

    if (preg_match('#/graphql$#', $baseUrl)) {
        return $baseUrl;
    }

    $baseUrl = preg_replace('#/rest$#', '', $baseUrl) ?? $baseUrl;
    $baseUrl = rtrim($baseUrl, '/');

    return $baseUrl . '/graphql';
}

function resolveTwentyEndpoint(string $endpoint, string $defaultSegment): string
{
    $normalizedEndpoint = trim($endpoint);
    if ($normalizedEndpoint === '') {
        return $defaultSegment;
    }

    if (preg_match('#^https?://#', $normalizedEndpoint)) {
        return $normalizedEndpoint;
    }

    $normalizedEndpoint = ltrim($normalizedEndpoint, '/');
    if (str_starts_with($normalizedEndpoint, 'rest/') || str_starts_with($normalizedEndpoint, 'graphql/')) {
        return $normalizedEndpoint;
    }

    return $normalizedEndpoint;
}

function twentyApiRequest(string $method, string $endpoint, ?array $data = null, ?array $query = null): array
{
    $apiKey = $_ENV['TWENTY_API_KEY'] ?? $_SERVER['TWENTY_API_KEY'] ?? getenv('TWENTY_API_KEY');
    if (!$apiKey) {
        return ['success' => false, 'error' => 'TWENTY_API_KEY não definida no arquivo .env.'];
    }

    $baseUrl = normalizeTwentyBaseUrl($_ENV['TWENTY_BASE_URL'] ?? $_SERVER['TWENTY_BASE_URL'] ?? getenv('TWENTY_BASE_URL') ?: 'https://api.twenty.com');
    $resolvedEndpoint = resolveTwentyEndpoint($endpoint, 'people');
    $trimmedEndpoint = ltrim($resolvedEndpoint, '/');

    if (preg_match('#^https?://#', $resolvedEndpoint)) {
        $url = $resolvedEndpoint;
    } elseif (preg_match('#/rest$#', $baseUrl)) {
        $url = $baseUrl . '/' . ($trimmedEndpoint === 'rest' || str_starts_with($trimmedEndpoint, 'rest/') ? $trimmedEndpoint : $trimmedEndpoint);
    } elseif (preg_match('#/graphql$#', $baseUrl)) {
        $url = $baseUrl . '/' . ($trimmedEndpoint === 'graphql' || str_starts_with($trimmedEndpoint, 'graphql/') ? $trimmedEndpoint : $trimmedEndpoint);
    } else {
        $url = $baseUrl . '/rest/' . $trimmedEndpoint;
    }

    if (!empty($query) && is_array($query)) {
        $queryString = http_build_query($query);
        if ($queryString !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?') . $queryString;
        }
    }

    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    ];

    switch (strtoupper($method)) {
        case 'POST':
            $options[CURLOPT_POST] = true;
            if ($data !== null) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            break;
        case 'PATCH':
            $options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
            if ($data !== null) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            break;
        case 'DELETE':
            $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
            break;
        case 'GET':
            break;
        default:
            return ['success' => false, 'error' => "Método HTTP '{$method}' não suportado."];
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    logDebug("Request: {$method} {$url}");
    if ($data !== null) {
        logDebug("Payload: " . json_encode($data));
    }
    logDebug("Response Code: {$httpCode}");
    logDebug("Response Body: {$response}");

    if ($curlError) {
        return ['success' => false, 'error' => 'Erro cURL: ' . $curlError];
    }

    $decodedResponse = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'httpCode' => $httpCode,
            'data' => $decodedResponse['data'] ?? $decodedResponse,
        ];
    }

    $errorMessage = "HTTP {$httpCode}";
    if (isset($decodedResponse['errors']) && is_array($decodedResponse['errors'])) {
        $errors = [];
        foreach ($decodedResponse['errors'] as $err) {
            $errors[] = $err['detail'] ?? $err['title'] ?? 'Erro desconhecido';
        }
        $errorMessage = implode('; ', $errors);
    } elseif (isset($decodedResponse['message'])) {
        $errorMessage = $decodedResponse['message'];
    }

    // Add detailed error info for debugging
    $details = [];
    $details[] = "URL: {$url}";
    $details[] = "Method: {$method}";
    if ($data !== null) {
        $details[] = "Payload: " . json_encode($data);
    }
    if (is_array($decodedResponse)) {
        $details[] = "Response: " . json_encode($decodedResponse);
    }

    return [
        'success' => false,
        'httpCode' => $httpCode,
        'error' => $errorMessage,
        'data' => $decodedResponse,
        'details' => $details,
    ];
}

function extractEmailCandidates(array $person): array
{
    $emails = [];

    // Check nested emails structure (emails.primaryEmail)
    if (isset($person['emails']) && is_array($person['emails'])) {
        if (!empty($person['emails']['primaryEmail'])) {
            $emails[] = (string)$person['emails']['primaryEmail'];
        }

        if (!empty($person['emails']['additionalEmails']) && is_array($person['emails']['additionalEmails'])) {
            foreach ($person['emails']['additionalEmails'] as $entry) {
                if (is_string($entry) && $entry !== '') {
                    $emails[] = $entry;
                    continue;
                }

                if (is_array($entry) && !empty($entry['value'])) {
                    $emails[] = (string)$entry['value'];
                }
            }
        }
    }

    // Check flat email fields as fallback
    if (!empty($person['primaryEmail'])) {
        $emails[] = (string)$person['primaryEmail'];
    }

    if (!empty($person['email'])) {
        $emails[] = (string)$person['email'];
    }

    // Filter empty strings and return unique emails
    $emails = array_filter($emails, function($email) {
        return !empty($email) && is_string($email);
    });

    return array_values(array_unique(array_map('strtolower', $emails)));
}

function findPersonByEmail(string $email)
{
    $searchEmail = strtolower(trim($email));

    $query = <<<'GQL'
query FindPersonByEmail($email: String!) {
  people(first: 1, filter: { emails: { primaryEmail: { eq: $email } } }) {
    edges {
      node {
        id
        name {
          firstName
          lastName
        }
        emails {
          primaryEmail
        }
      }
    }
  }
}
GQL;

    $result = twentyApiRequest('POST', buildTwentyGraphqlUrl(), [
        'query' => $query,
        'variables' => ['email' => $searchEmail],
    ]);

    if (!$result['success']) {
        $restResult = twentyApiRequest('GET', 'people', null, ['limit' => 1000]);
        if (!$restResult['success']) {
            return false;
        }

        $responseData = $restResult['data'] ?? [];
        $people = $responseData['people'] ?? $responseData['data'] ?? $responseData;
        if (!is_array($people)) {
            return false;
        }

        foreach ($people as $person) {
            if (!is_array($person)) {
                continue;
            }

            foreach (extractEmailCandidates($person) as $candidateEmail) {
                if ($candidateEmail === $searchEmail) {
                    return $person;
                }
            }
        }

        return false;
    }

    $edges = $result['data']['people']['edges'] ?? [];
    if (!is_array($edges) || empty($edges[0]['node']) || !is_array($edges[0]['node'])) {
        return false;
    }

    return $edges[0]['node'];
}

function findDeletedPersonByEmail(string $email)
{
        $searchEmail = strtolower(trim($email));

        $query = <<<'GQL'
query FindDeletedPersonByEmail($email: String!) {
    people(first: 1, filter: { emails: { primaryEmail: { eq: $email } }, not: { deletedAt: { is: "NULL" } } }) {
        edges {
            node {
                id
                name {
                    firstName
                    lastName
                }
                emails {
                    primaryEmail
                }
                deletedAt
            }
        }
    }
}
GQL;

        $result = twentyApiRequest('POST', buildTwentyGraphqlUrl(), [
                'query' => $query,
                'variables' => ['email' => $searchEmail],
        ]);

        if (!$result['success']) {
                return false;
        }

        $edges = $result['data']['people']['edges'] ?? [];
        if (!is_array($edges) || empty($edges[0]['node']) || !is_array($edges[0]['node'])) {
                return false;
        }

        return $edges[0]['node'];
}

function restorePersonById(string $personId): array
{
        $mutation = <<<'GQL'
mutation RestorePerson($personId: UUID!) {
    restorePerson(id: $personId) {
        id
        deletedAt
    }
}
GQL;

        return twentyApiRequest('POST', buildTwentyGraphqlUrl(), [
                'query' => $mutation,
                'variables' => ['personId' => $personId],
        ]);
}

function findPersonByName(string $firstName, string $lastName)
{
    $result = twentyApiRequest('GET', 'people', null, ['limit' => 1000]);
    if (!$result['success']) {
        return false;
    }

    $responseData = $result['data'] ?? [];
    $people = $responseData['people'] ?? $responseData['data'] ?? $responseData;
    
    if (!is_array($people)) {
        return false;
    }

    $searchFirstName = strtolower(trim($firstName));
    $searchLastName = strtolower(trim($lastName));
    $fullSearchName = trim($firstName . ' ' . $lastName);
    $fullSearchNameLower = strtolower($fullSearchName);
    
    foreach ($people as $person) {
        if (!is_array($person)) {
            continue;
        }

        $personFirstName = strtolower(trim($person['name']['firstName'] ?? ''));
        $personLastName = strtolower(trim($person['name']['lastName'] ?? ''));
        $personFullName = strtolower(trim(($person['name']['firstName'] ?? '') . ' ' . ($person['name']['lastName'] ?? '')));
        
        // Exact match with split names
        if ($personFirstName === $searchFirstName && $personLastName === $searchLastName) {
            return $person;
        }
        
        // Match if both names are in the first name (not split)
        if ($personFullName === $fullSearchNameLower && $personLastName === '') {
            return $person;
        }
        
        // Match if the full name is in the person's first name
        if (strpos($personFirstName, $searchFirstName) === 0 && 
            (empty($searchLastName) || strpos($personFirstName, $searchLastName) !== false)) {
            return $person;
        }
    }

    return false;
}

function buildPersonPayload(array $owner): array
{
    $nameParts = preg_split('/\s+/', trim($owner['nome']), 2) ?: [trim($owner['nome'])];
    $firstName = $nameParts[0] ?? '';
    $lastName = $nameParts[1] ?? '';

    $payload = [
        'name' => [
            'firstName' => $firstName,
            'lastName' => $lastName,
        ],
    ];

    $email = trim($owner['email'] ?? '');
    if ($email !== '') {
        $payload['emails'] = [
            'primaryEmail' => $email,
        ];
    }

    return $payload;
}

function createOrUpdatePerson(array $owner): array
{
    $personPayload = buildPersonPayload($owner);
    $personEndpoint = $_ENV['TWENTY_PERSON_OBJECT_NAME'] ?? $_SERVER['TWENTY_PERSON_OBJECT_NAME'] ?? getenv('TWENTY_PERSON_OBJECT_NAME') ?: 'people';

    // Try to find existing person by email
    $existingPerson = findPersonByEmail($owner['email']);

    if ($existingPerson) {
        $personId = $existingPerson['id'] ?? null;
        if (!$personId) {
            $failure = ['success' => false, 'error' => 'Pessoa existente sem ID retornado pelo Twenty.'];
            logTwentyError('createOrUpdatePerson:existing-person-missing-id', $failure);
            return $failure;
        }

        $result = twentyApiRequest('PATCH', $personEndpoint . '/' . $personId, $personPayload);
        if ($result['success']) {
            return ['success' => true, 'personId' => $personId];
        }

        $errorMsg = $result['error'] ?? 'Erro desconhecido';
        logTwentyError('createOrUpdatePerson:update-person', $result);
        return ['success' => false, 'error' => 'Falha ao atualizar pessoa: ' . $errorMsg];
    }

    $deletedPerson = findDeletedPersonByEmail($owner['email']);
    if ($deletedPerson) {
        $personId = $deletedPerson['id'] ?? null;
        if (!$personId) {
            $failure = ['success' => false, 'error' => 'Pessoa deletada sem ID retornado pelo Twenty.'];
            logTwentyError('createOrUpdatePerson:deleted-person-missing-id', $failure);
            return $failure;
        }

        $restoreResult = restorePersonById($personId);
        if ($restoreResult['success']) {
            $updateResult = twentyApiRequest('PATCH', $personEndpoint . '/' . $personId, $personPayload);
            if ($updateResult['success']) {
                return ['success' => true, 'personId' => $personId];
            }

            logTwentyError('createOrUpdatePerson:restore-then-update', $updateResult);
            $updateError = $updateResult['error'] ?? 'Erro desconhecido';
            return ['success' => false, 'error' => 'Falha ao atualizar pessoa restaurada: ' . $updateError];
        }

        logTwentyError('createOrUpdatePerson:restore-person', $restoreResult);
        $restoreError = $restoreResult['error'] ?? 'Erro desconhecido';
        return ['success' => false, 'error' => 'Falha ao restaurar pessoa removida: ' . $restoreError];
    }

    // Person doesn't exist, create new one
    $result = twentyApiRequest('POST', $personEndpoint, $personPayload);
    if ($result['success']) {
        $responseData = $result['data'] ?? [];
        $personId = null;

        if (is_array($responseData)) {
            if (!empty($responseData['id'])) {
                $personId = $responseData['id'];
            } elseif (!empty($responseData['data']['id'])) {
                $personId = $responseData['data']['id'];
            } elseif (!empty($responseData['person']['id'])) {
                $personId = $responseData['person']['id'];
            } elseif (!empty($responseData['record']['id'])) {
                $personId = $responseData['record']['id'];
            } elseif (!empty($responseData['data']['record']['id'])) {
                $personId = $responseData['data']['record']['id'];
            } elseif (!empty($responseData['createPerson']['id'])) {
                $personId = $responseData['createPerson']['id'];
            } elseif (!empty($responseData['data']['createPerson']['id'])) {
                $personId = $responseData['data']['createPerson']['id'];
            }
        }

        if ($personId) {
            return ['success' => true, 'personId' => $personId];
        }

        $failure = ['success' => false, 'error' => 'Resposta de criação sem ID.', 'data' => $result['data'] ?? null];
        logTwentyError('createOrUpdatePerson:create-person-missing-id', $failure);
        return $failure;
    }

    if (isDuplicateTwentyError($result)) {
        $deletedPerson = findDeletedPersonByEmail($owner['email']);
        if ($deletedPerson) {
            $personId = $deletedPerson['id'] ?? null;
            if ($personId) {
                $restoreResult = restorePersonById($personId);
                if ($restoreResult['success']) {
                    $updateResult = twentyApiRequest('PATCH', $personEndpoint . '/' . $personId, $personPayload);
                    if ($updateResult['success']) {
                        return ['success' => true, 'personId' => $personId];
                    }

                    logTwentyError('createOrUpdatePerson:duplicate-restore-then-update', $updateResult);
                    $updateError = $updateResult['error'] ?? 'Erro desconhecido';
                    return ['success' => false, 'error' => 'Falha ao atualizar pessoa restaurada: ' . $updateError];
                }

                logTwentyError('createOrUpdatePerson:duplicate-restore-person', $restoreResult);
                $restoreError = $restoreResult['error'] ?? 'Erro desconhecido';
                return ['success' => false, 'error' => 'Falha ao restaurar pessoa removida: ' . $restoreError];
            }
        }

        $existingPerson = findPersonByEmail($owner['email']);
        if ($existingPerson) {
            $personId = $existingPerson['id'] ?? null;
            if ($personId) {
                $updateResult = twentyApiRequest('PATCH', $personEndpoint . '/' . $personId, $personPayload);
                if ($updateResult['success']) {
                    return ['success' => true, 'personId' => $personId];
                }

                logTwentyError('createOrUpdatePerson:duplicate-then-update', $updateResult);
                $updateError = $updateResult['error'] ?? 'Erro desconhecido';
                return ['success' => false, 'error' => 'Falha ao atualizar pessoa duplicada: ' . $updateError];
            }
        }
    }

    $errorMsg = $result['error'] ?? 'Erro desconhecido';
    logTwentyError('createOrUpdatePerson:create-person', $result);
    return ['success' => false, 'error' => 'Falha ao criar pessoa: ' . $errorMsg];
}

function getPetFieldConfig(): array
{
    return [
        'objectName' => 'pets',
        'nameField' => 'name',
        'birthdayField' => 'birthDate',
        'sizeField' => 'size',
        'healthField' => 'healthCondition',
        'reasonField' => 'whyYouUse',
        'firstTimeField' => 'firstTimeUsing',
        'ageField' => 'age',
        'ownerRelationField' => 'petOnwerId',
    ];
}

function buildPetRecordPayload(array $pet, string $personId, array $fieldConfig, array $condicaoMap): array
{
    $healthValue = $pet['condicao_saude'];
    if (isset($condicaoMap[$pet['condicao_saude']])) {
        $healthValue = $condicaoMap[$pet['condicao_saude']];
    }

    return [
        $fieldConfig['nameField'] => $pet['nome'],
        $fieldConfig['birthdayField'] => $pet['birthday'],
        $fieldConfig['sizeField'] => $pet['porte'],
        $fieldConfig['healthField'] => [$healthValue],
        $fieldConfig['reasonField'] => $pet['motivo'],
        $fieldConfig['firstTimeField'] => (bool)$pet['primeira_vez_condropure'],
        $fieldConfig['ageField'] => (int)$pet['idade'],
        $fieldConfig['ownerRelationField'] => $personId,
    ];
}

function createPets(string $personId, array $pets): array
{
    $successCount = 0;
    $errors = [];
    $fieldConfig = getPetFieldConfig();

    $condicaoMap = [
        'artrite' => 'ARTRITE_ARTROSE',
        'displasia' => 'DISPLASIA_COXOFEMORAL',
        'dor_articular' => 'DOR_ARTICULAR',
        'cirurgia_ortopedica' => 'POS_CIRURGIA_ORTOPEDICA',
        'prevencao' => 'PREVENCAO_PET_SAUDAVEL',
        'outro' => 'OUTRO',
    ];

    foreach ($pets as $pet) {
        $petPayload = buildPetRecordPayload($pet, $personId, $fieldConfig, $condicaoMap);
        $result = twentyApiRequest('POST', $fieldConfig['objectName'], $petPayload);

        if ($result['success']) {
            $successCount++;
        } else {
            $errors[] = "Erro ao criar pet '{$pet['nome']}': " . ($result['error'] ?? 'Erro desconhecido.');
        }
    }

    if ($successCount === count($pets)) {
        return ['success' => true];
    }

    if ($successCount > 0) {
        return [
            'success' => true,
            'partial' => true,
            'message' => "{$successCount} de " . count($pets) . " pets criados com sucesso.",
            'errors' => $errors,
        ];
    }

    return [
        'success' => false,
        'error' => 'Falha ao criar todos os pets.',
        'errors' => $errors,
        'details' => $errors,
    ];
}

function sendToTwenty(array $owner, array $pets): array
{
    $personResult = createOrUpdatePerson($owner);
    if (!$personResult['success']) {
        return ['success' => false, 'message' => 'Falha no cadastro do tutor: ' . ($personResult['error'] ?? ''), 'details' => $personResult];
    }

    $personId = $personResult['personId'];
    $petsResult = createPets($personId, $pets);
    if (!$petsResult['success']) {
        return ['success' => false, 'message' => 'Falha ao cadastrar pets: ' . ($petsResult['error'] ?? ''), 'details' => $petsResult];
    }

    if (!empty($petsResult['partial'])) {
        return [
            'success' => true,
            'message' => 'Tutor cadastrado com sucesso, mas alguns pets apresentaram erro: ' . implode(' ', $petsResult['errors'] ?? [])
        ];
    }

    return ['success' => true, 'message' => 'Dados enviados para o Twenty CRM com sucesso.'];
}