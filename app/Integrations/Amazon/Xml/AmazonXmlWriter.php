<?php

namespace App\Integrations\Amazon\Xml;

class AmazonXmlWriter
{
    protected \XMLWriter $x; protected int $msgId = 0; protected string $merchant;


    public function __construct(string $merchantIdentifier)
    {
        $this->merchant = $merchantIdentifier;
        $this->x = new \XMLWriter();
        $this->x->openMemory();
        $this->x->startDocument('1.0', 'UTF-8');
        $this->x->setIndent(true);
        $this->x->startElement('AmazonEnvelope');
        $this->elem('Header', function(){
            $this->elem('DocumentVersion', null, '1.01');
            $this->elem('MerchantIdentifier', null, $this->merchant);
        });
    }

    public function startMessageType(string $type): void
    {
        $this->elem('MessageType', null, $type);
    }


    public function message(callable $cb, ?string $operation = null): void
    {
        $this->x->startElement('Message');
        $this->elem('MessageID', null, (string)(++$this->msgId));
        if ($operation) $this->elem('OperationType', null, $operation);
        $cb($this);
        $this->x->endElement();
    }

    public function elem(string $name, ?callable $cb = null, ?string $text = null, array $attrs = []): void
    {
        $this->x->startElement($name);
        foreach ($attrs as $k=>$v) $this->x->writeAttribute($k, $v);
        if ($cb) { $cb($this); }
        elseif ($text !== null) { $this->x->text($text); }
        $this->x->endElement();
    }


    public function end(): string
    {
        $this->x->endElement(); // AmazonEnvelope
        $this->x->endDocument();
        return $this->x->outputMemory();
    }
}
