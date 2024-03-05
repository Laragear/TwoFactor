<?php

namespace Laragear\TwoFactor\Models\Concerns;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

use function array_values;
use function chunk_split;
use function config;
use function http_build_query;
use function rawurlencode;
use function strtoupper;
use function trim;

trait SerializesSharedSecret
{
    /**
     * Returns the Shared Secret as a URI.
     *
     * @return string
     */
    public function toUri(): string
    {
        $query = http_build_query([
            'issuer' => Str::before($this->attributes['label'], ':'),
            'label' => $this->attributes['label'],
            'secret' => $this->shared_secret,
            'algorithm' => strtoupper($this->attributes['algorithm']),
            'digits' => $this->attributes['digits'],
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/'.rawurlencode($this->attributes['label'])."?$query";
    }

    /**
     * Returns the Shared Secret as a QR Code in SVG format.
     *
     * @return string
     */
    public function toQr(): string
    {
        [$size, $margin] = array_values(config('two-factor.qr_code'));

        return (
            new Writer(new ImageRenderer(new RendererStyle($size, $margin), new SvgImageBackEnd()))
        )->writeString($this->toUri());
    }

    /**
     * Returns the current object instance as a string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Returns the Shared Secret as a string.
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->shared_secret;
    }

    /**
     * Returns the Shared Secret as a string of 4-character groups separated by whitespace.
     *
     * @return string
     */
    public function toGroupedString(): string
    {
        return trim(chunk_split($this->toString(), 4, ' '));
    }

    /**
     * @inheritDoc
     */
    public function render(): string
    {
        return $this->toQr();
    }
}
