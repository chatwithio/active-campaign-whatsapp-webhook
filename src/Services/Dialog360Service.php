<?php
namespace App\Services;


use App\Services\TemplateDto;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class Dialog360Service
{
    const END_POINT_DEV = 'https://waba-sandbox.360dialog.io/v1';

    const END_POINT_PROD = 'https://waba.360dialog.io/v1';

    const DEFAULT_NAMESPACE = '';

    private string $url;

    private LoggerInterface $logger;


    private Client $client;

    private bool $isDebug;

    private string $devWaHeaderImage;

    private string $devWaHeaderVideo;

    private string $devWaHeaderDocument;

    public function __construct(
        LoggerInterface $logger,
        bool $isDebug = false,
        string $devWaHeaderImage = '',
        string $devWaHeaderVideo = '',
        string $devWaHeaderDocument = ''
    ) {
        $this->logger = $logger;
        $this->client = new Client();
        $this->isDebug = $isDebug;
        $this->url = self::END_POINT_PROD;
        $this->devWaHeaderImage = $devWaHeaderImage;
        $this->devWaHeaderVideo = $devWaHeaderVideo;
        $this->devWaHeaderDocument = $devWaHeaderDocument;
    }

    public function getTemplates(array $dataConfig): array
    {
        try {
            $response = $this->client->request('GET',
                $this->url . '/configs/templates',
                [
                    'headers'     => [
                        'D360-API-KEY' => $dataConfig['apiKey'],
                        'Content-type' => 'application/json'
                    ],
                    "json"        => [],
                    'http_errors' => false
                ]);
            $content = $response->getBody()->getContents();
            $result = json_decode($content);
            if (gettype($result) === 'object') {
                if (!property_exists($result, 'waba_templates')) {
                    $this->logger->info($content);
                    throw new \Exception('No waba_templates exist');
                }
            } else {
                if (!array_key_exists('waba_templates', $result)) {
                    $this->logger->info($content);
                    throw new \Exception('No waba_templates found');
                }
            }

            $templates = [];
            foreach ($result->waba_templates as $template) {
                if (strtolower($template->status) === 'approved') {
                    $templates[] = TemplateDto::dialog360Template($template);
                }
            }
            if (count($templates) == 0) {
                throw new \Exception('No approved templates found');
            }

            return [
                'isSuccess' => true,
                'templates' => $templates
            ];
        } catch (GuzzleException $e) {
            $this->logger->info($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
        }

        return [
            'isSuccess' => false,
            'templates' => []
        ];
    }

    public function sendTemplateMessage(array $dataConfig, ?string $userLocale = 'en'): array
    {
        $phoneTo = $dataConfig['phoneTo'];
        $header = null;
        $buttons = [];
        $language = array_key_exists('language', $dataConfig) ? $dataConfig['language'] : 'en';

        if ($dataConfig['templateDto'] === null) {
            $requestTemplates = $this->getTemplates($dataConfig);

            if (!$requestTemplates['isSuccess']) {
                return [
                    'isSent' => false,
                    'id'     => null,
                    'error'  => [
                        'isError'    => false,
                        'code'       => null,
                        'message'    => null,
                        'rawMessage' => null
                    ]
                ];
            }

            $templates = $requestTemplates['templates'];

            $namespace = self::DEFAULT_NAMESPACE;

            foreach ($templates as $template) {
                if ($template->name == $dataConfig['template'] && $template->language == $language) {
                    $namespace = $template->namespace;
                };
            }
        } else {
            $namespace = $dataConfig['templateDto']['id'];
            if (array_key_exists('header', $dataConfig['templateDto'])) {
                if ($dataConfig['templateDto']['header']) {
                    if ($dataConfig['templateDto']['header']['format'] === 'IMAGE') {
                        if ($this->isDebug) {
                            $header = ['image' => $this->devWaHeaderImage];
                        } else {
                            $header = ['image' => $dataConfig['templateDto']['header']['link']];
                        }
                    }

                    if ($dataConfig['templateDto']['header']['format'] === 'VIDEO') {
                        if ($this->isDebug) {
                            $header = ['video' => $this->devWaHeaderVideo];
                        } else {
                            $header = ['video' => $dataConfig['templateDto']['header']['link']];
                        }
                    }

                    if ($dataConfig['templateDto']['header']['format'] === 'DOCUMENT') {
                        if ($this->isDebug) {
                            $header = ['document' => $this->devWaHeaderDocument];
                        } else {
                            $header = ['document' => $dataConfig['templateDto']['header']['link']];
                        }
                    }
                }
            }

            if (array_key_exists('buttons', $dataConfig['templateDto'])) {
                $buttons = $dataConfig['templateDto']['buttons'];
            }
        }

        $contact = $this->checkContact($phoneTo, $dataConfig['apiKey']);

        if (!$contact['isValid']) {
            return [
                'isSent' => false,
                'id'     => null,
                'error'  => [
                    'isError'    => false,
                    'code'       => null,
                    'message'    => null,
                    'rawMessage' => null
                ]
            ];
        }

        $payload = [
            'to'       => $contact['id'],
            'type'     => 'template',
            'template' => [
                'namespace'  => $namespace,
                'language'   => [
                    'policy' => 'deterministic',
                    'code'   => $language
                ],
                'name'       => $dataConfig['template'],
                'components' => [
                    [
                        'type'       => 'body',
                        'parameters' => $this->buildParams($dataConfig['parameters'])
                    ]
                ]
            ]
        ];

        $payload = $this->makeHeader($header, $payload);
        $payload = $this->makeButtons($buttons, $payload);

        try {
            $jsonPayload = json_encode($payload);
            $response = $this->client->request('POST',
                $this->url . '/messages',
                [
                    'headers' => [
                        'D360-API-KEY' => $dataConfig['apiKey'],
                        'Content-type' => 'application/json',
                        'Accept' => 'application/json'
                    ],
                    //'json'    => $payload
                    'body'    => $jsonPayload
                ]);
            $result = json_decode($response->getBody()->getContents());
            return [
                'isSent' => (bool)$result->messages[0]->id,
                'id'     => $result->messages[0]->id,
                'error'  => [
                    'isError'    => false,
                    'code'       => null,
                    'message'    => null,
                    'rawMessage' => null
                ]
            ];
        } catch (GuzzleException $e) {
           if($jsonPayload){
                $this->logger->info($jsonPayload);
            }
            $this->logger->info($e->getMessage());
            $errors = $this->getErrorDetails($e->getMessage());

            return [
                'isSent' => false,
                'id'     => null,
                'error'  => [
                    'isError'    => true,
                    'code'       => $errors['code'],
                    'message'    => $errors['message'],
                    'rawMessage' => $e->getMessage()
                ]
            ];
        }
    }

    public function sendTemplateMessageRaw($apiKey, $phoneTo, $namespace, $template, $language, $parameters, $buttons = []): array
    {
        $contact = $this->checkContact($phoneTo, $apiKey);

        if (!$contact['isValid']) {
            return [
                'isSent' => false,
                'id'     => null,
                'error'  => [
                    'isError'    => false,
                    'code'       => null,
                    'message'    => null,
                    'rawMessage' => null
                ]
            ];
        }

        $components = [
            [
                'type'       => 'body',
                'parameters' => $this->buildParams($parameters)
            ]
        ];

        $payload = [
            'to'       => $contact['id'],
            'type'     => 'template',
            'template' => [
                'namespace' => $namespace,
                'language'  => [
                    'policy' => 'deterministic',
                    'code'   => $language
                ],
                'name'      => $template,
            ]
        ];

        if (count($buttons) > 0) {
            $components = array_merge($components, $buttons);
        }

        $payload['template']['components'] = $components;

        try {
            $response = $this->client->request('POST',
                $this->url . '/messages',
                [
                    'headers' => [
                        'D360-API-KEY' => $apiKey,
                        'Content-type' => 'application/json'
                    ],
                    'json'    => $payload
                ]);

            $result = json_decode($response->getBody()->getContents());

            return [
                'isSent' => (bool)$result->messages[0]->id,
                'id'     => $result->messages[0]->id,
                'error'  => [
                    'isError'    => false,
                    'code'       => null,
                    'message'    => null,
                    'rawMessage' => null
                ]
            ];
        } catch (GuzzleException $e) {
            $this->logger->info($e->getMessage());
            $errors = $this->getErrorDetails($e->getMessage());

            return [
                'isSent' => false,
                'id'     => null,
                'error'  => [
                    'isError'    => true,
                    'code'       => $errors['code'],
                    'message'    => $errors['message'],
                    'rawMessage' => $e->getMessage()
                ]
            ];
        }
    }

    public function addWebhook(array $dataConfig): array
    {
        try {
            $response = $this->client->request('POST',
                $this->url . '/configs/webhook',
                [
                    'headers' => [
                        'D360-API-KEY' => $dataConfig['apiKey'],
                        'Content-type' => 'application/json'
                    ],
                    'json'    => [
                        'url' => $dataConfig['url']
                    ]
                ]);

            $result = json_decode($response->getBody()->getContents());

            return [
                'id'     => $result->url,
                'active' => $result->url === $dataConfig['url'],
                'url'    => $dataConfig['url']
            ];
        } catch (GuzzleException $e) {
            $this->logger->info($e->getMessage());
        }

        return [
            'id'     => null,
            'active' => false,
            'url'    => ''
        ];
    }

    public function deleteWebhook(array $dataConfig): array
    {
        try {
            $this->client->request('POST',
                $this->url . '/configs/webhook',
                [
                    'headers' => [
                        'D360-API-KEY' => $dataConfig['apiKey'],
                        'Content-type' => 'application/json'
                    ],
                    'json'    => [
                        'url' => 'https://www.example.com/webhook-fake' //fake url webhook
                    ]
                ]);

            return [
                'isDelete' => true
            ];
        } catch (GuzzleException $e) {
            $this->logger->info($e->getMessage());
        }

        return [
            'isDelete' => false
        ];
    }

    private function checkContact($phone, $apiKey): array
    {
        try {
            $response = $this->client->request('POST',
                $this->url . '/contacts',
                [
                    'headers' => [
                        'D360-API-KEY' => $apiKey,
                        'Content-type' => 'application/json'
                    ],
                    "json"    => [
                        "blocking"    => "wait",
                        "contacts"    => ["+" . $phone],
                        "force_check" => true
                    ],
                ]);

            $result = json_decode($response->getBody()->getContents())->contacts[0];

            return [
                'isValid' => $result->status === 'valid',
                'id'      => $result->status === 'valid' ? $result->wa_id : null
            ];
        } catch (GuzzleException $e) {
            $this->logger->info($e->getMessage());
        }

        return [
            'isValid' => false,
            'id'      => null
        ];
    }

    public function getWebhook(array $dataConfig): array
    {
        try {
            $response = $this->client->request('GET',
                $this->url . '/configs/webhook',
                [
                    'headers' => [
                        'D360-API-KEY' => $dataConfig['apiKey'],
                        'Content-type' => 'application/json'
                    ]
                ]);

            $result = json_decode($response->getBody()->getContents());

            $hasWebHook = property_exists($result, 'url', );

            return [
                'hasWebhook' => $hasWebHook && $result->url !== '',
                'url'        => $hasWebHook && $result->url ? $result->url : null
            ];
        } catch (GuzzleException $e) {
            $this->logger->info($e->getMessage());
        }

        return [
            'hasWebhook' => false,
            'url'        => null,
        ];
    }

    public function checkUrlWebhook(string $url): bool
    {
        try {
            $response = $this->client->post($url, []);
            $result = json_decode($response->getBody()->getContents());

            return array_key_exists('url', $result);
        } catch (GuzzleException $e) {

        }

        return false;
    }

    private function buildParams($placeholders): array
    {
        $arr = [];
        foreach ($placeholders as $placeholder) {
            $arr[] = [
                "type" => "text",
                "text" => $placeholder
            ];
        }

        return $arr;
    }

    public function makeHeader($header, $payload): array
    {
        if (!empty($header)) {

            $processed = null;

            if (isset($header['video'])) {
                $processed = ["type" => "video", "video" => ["link" => null]];
                $processed["video"]['link'] = $header['video'];
            }

            if (isset($header['image'])) {
                $processed = ["type" => "image", "image" => ["link" => null]];
                $processed["image"]['link'] = $header['image'];
            }

            if (isset($header['document'])) {
                $processed = ["type" => "document", "document" => ["link" => null]];
                $processed["document"]['link'] = $header['document'];
                try {
                    $filename = basename(parse_url($header['document'], PHP_URL_PATH));
                    $processed["document"]['filename'] = $filename;
                } catch (\Exception $exception) {
                    $processed["document"]['filename'] = $header['document'];
                }
            }

            if ($processed) {
                $processed = [
                    "type"       => "header",
                    "parameters" => [$processed]
                ];
            }

            $payload["template"]["components"][] = $processed;
        }

        return $payload;
    }

    public function makeButtons($buttons, $payload): array
    {
        if (count($buttons) === 0) {
            return $payload;
        }

        foreach ($buttons as $key => $button) {

            $buttonProcessed = [
                'type'     => 'button',
                'sub_type' => strtolower($button['subType']),
                'index'    => (string)$key
            ];

            if ($button['subType'] === 'QUICK_REPLY') {
                $buttonProcessed['parameters'] = [
                    'type'    => 'payload',
                    'payload' => array_key_exists('uuid', $button) ? $button['uuid'] : ''
                ];

                $payload["template"]["components"][] = $buttonProcessed;
            } else {
                $buttonProcessed['parameters'] = [
                    [
                        'type' => 'text',
                        'text' => array_key_exists('suffix', $button) ? $button['suffix'] : ''
                    ]
                ];

                if ($button['hasVar']) {
                    $payload["template"]["components"][] = $buttonProcessed;
                }
            }
        }

        return $payload;
    }

    public function getErrorDetails(string $error, $locale = 'en'): array
    {
        $errorMessage = 'unknown';
        return [
            'code'    => $error,
            'message' => $errorMessage
        ];
    }
}


