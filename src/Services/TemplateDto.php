<?php
declare(strict_types=1);

namespace App\Services;

class TemplateDto
{
    //$id is used for sender template
    public string $id;

    public string $name;

    public string $language;

    public string $status;

    public $data = null;

    public ?array $header = null;

    public ?string $body = null;

    public ?string $footer = null;

    public ?string $bodyReplaced = null;

    public array $parameters = []; // ["{{1}}", "{{2}}"]

    public array $placeholders = [];// ["Hello", "WhatChatIO"]

    public array $buttons = [];

    public array $buttonsReplaced = [];

    /** @var ClientParametersDto[] */
    public array $clientParameters = []; //

    public function __construct(
        string $id,
        string $name,
        string $language,
        string $status,
        $data = null,
        ?array $header = null,
        ?string $body = null,
        ?string $footer = null,
        ?array $parameters = [],
        ?array $buttons = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->language = $language;
        $this->status = $status;
        $this->data = $data;
        $this->header = $header;
        $this->body = $body;
        $this->footer = $footer;
        $this->parameters = $parameters;
        $this->buttons = $buttons;
    }

    public static function dialog360Template($object): self
    {
        $header = null;
        $body = null;
        $footer = null;
        $buttons = [];
        foreach ($object->components as $item) {
            if ($item->type === 'HEADER') {
                if (property_exists($item, 'format')) {
                    if ($item->format === 'IMAGE' || $item->format === 'VIDEO' || $item->format === 'DOCUMENT') {

                        $header = [
                            'format' => $item->format,
                            'link'   => null
                        ];

                        if (property_exists($item, 'example')) {
                            if (property_exists($item->example, 'header_handle')) {
                                if (is_array($item->example->header_handle)) {
                                    $header = [
                                        'format' => $item->format,
                                        'link'   => $item->example->header_handle[0]
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            if ($item->type === 'BODY') {
                $body = $item->text;
            }
            if ($item->type === 'FOOTER') {
                $footer = $item->text;
            }
            if ($item->type === 'BUTTONS') {
                foreach ($item->buttons as $key => $button) {

                    $text = null;
                    $textWithoutVar = null;
                    $hasVar = false;

                    if ($button->type === 'URL' || $button->type === 'PHONE_NUMBER') {
                        $text = $button->type === 'URL' ? $button->url : $button->phone_number;
                        $regex = '/{{\s*\d+\s*}}/';
                        if (preg_match($regex, $text)) {
                            $hasVar = true;
                        }
                        $textWithoutVar = preg_replace($regex, '$1', $text);
                    }

                    $buttons[] = [
                        'id'              => 'button_' . $key,
                        'name'            => $button->text,
                        'subType'         => $button->type,
                        'text'            => $text,
                        'textWithoutVar'  => $textWithoutVar,
                        'hasVar'          => $hasVar,
                        'suffix'          => null,
                        'clientParameter' => null
                    ];
                }
            }
        }

        preg_match_all('/{{\d+}}/', $body, $matches, PREG_PATTERN_ORDER);
        $parameters = array_key_exists(0, $matches) ? $matches[0] : [];

        //dialog360 all items same id(namespace)...
        return new TemplateDto($object->namespace, $object->name, $object->language, $object->status, $object->components, $header, $body, $footer, $parameters, $buttons);
    }

    public static function gupshupTemplate($object): self
    {
        preg_match_all('/{{\d+}}/', $object->data, $matches, PREG_PATTERN_ORDER);
        $parameters = array_key_exists(0, $matches) ? $matches[0] : [];

        return new TemplateDto($object->id, $object->elementName, $object->languageCode, $object->status, $object->data, null, $object->data, null, $parameters, []);
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'language'         => $this->language,
            'status'           => $this->status,
            'data'             => $this->data,
            'header'           => $this->header,
            'body'             => $this->body,
            'footer'           => $this->footer,
            'bodyReplaced'     => $this->bodyReplaced,
            'parameters'       => $this->parameters,
            'placeholders'     => $this->placeholders,
            'clientParameters' => $this->clientParameters,
            'buttons'          => $this->buttons
        ];
    }
}
