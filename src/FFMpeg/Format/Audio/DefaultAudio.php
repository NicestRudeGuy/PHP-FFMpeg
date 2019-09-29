<?php

declare(strict_types=1);

/*
 * This file is part of PHP-FFmpeg.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FFMpeg\Format\Audio;

use Evenement\EventEmitter;
use FFMpeg\Exception\InvalidArgumentException;
use FFMpeg\FFProbe;
use FFMpeg\Format\AudioInterface;
use FFMpeg\Format\ProgressableInterface;
use FFMpeg\Format\ProgressListener\AudioProgressListener;
use FFMpeg\Media\MediaTypeInterface;

abstract class DefaultAudio extends EventEmitter implements AudioInterface, ProgressableInterface
{
    /** @var string */
    protected $audioCodec;

    /** @var int */
    protected $audioKiloBitrate = 128;

    /** @var int */
    protected $audioChannels = null;

    /**
     * @inheritDoc
     */
    public function getExtraParams()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAudioCodec()
    {
        return $this->audioCodec;
    }

    /**
     * Sets the audio codec, Should be in the available ones, otherwise an
     * exception is thrown.
     *
     * @param string $audioCodec
     *
     * @throws InvalidArgumentException
     */
    public function setAudioCodec($audioCodec)
    {
        if (!in_array($audioCodec, $this->getAvailableAudioCodecs())) {
            throw new InvalidArgumentException(sprintf(
                'Wrong audiocodec value for %s, available formats are %s',
                $audioCodec,
                implode(', ', $this->getAvailableAudioCodecs())
            ));
        }

        $this->audioCodec = $audioCodec;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getAudioKiloBitrate()
    {
        return $this->audioKiloBitrate;
    }

    /**
     * Sets the kiloBitrate value.
     *
     * @param  int                  $kiloBitrate
     * @throws InvalidArgumentException
     */
    public function setAudioKiloBitrate($kiloBitrate)
    {
        if ($kiloBitrate < 1) {
            throw new InvalidArgumentException('Wrong kiloBitrate value');
        }

        $this->audioKiloBitrate = (int) $kiloBitrate;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getAudioChannels()
    {
        return $this->audioChannels;
    }

    /**
     * Sets the channels value.
     *
     * @param  int                  $channels
     * @throws InvalidArgumentException
     */
    public function setAudioChannels($channels)
    {
        if ($channels < 1) {
            throw new InvalidArgumentException('Wrong channels value');
        }

        $this->audioChannels = (int) $channels;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function createProgressListener(MediaTypeInterface $media, FFProbe $ffprobe, $pass, $total, $duration = 0)
    {
        $format = $this;
        $listener = new AudioProgressListener($ffprobe, $media->getPathfile(), $pass, $total, $duration);
        $listener->on('progress', function () use ($media, $format) {
            $format->emit('progress', array_merge([$media, $format], func_get_args()));
        });

        return [$listener];
    }

    /**
     * @inheritDoc
     */
    public function getPasses()
    {
        return 1;
    }
}
