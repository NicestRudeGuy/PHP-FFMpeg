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

namespace FFMpeg\Media;

use Alchemy\BinaryDriver\Exception\ExecutionFailureException;
use FFMpeg\Driver\FFMpegDriver;
use FFMpeg\Exception\InvalidArgumentException;
use FFMpeg\Exception\RuntimeException;
use FFMpeg\FFProbe;
use FFMpeg\Filters\Waveform\WaveformFilterInterface;
use FFMpeg\Filters\Waveform\WaveformFilters;

class Waveform extends AbstractMediaType implements IAudioOwned
{
    use TAudioOwned;

    const DEFAULT_COLOR = '#000000';

    /**
     * The audio class this waveform is created for
     * @var Audio
     */
    protected $audio;
    protected $width;
    protected $height;

    /**
     * @var string[]
     */
    protected $colors;

    public function __construct(Audio $audio, FFMpegDriver $driver, FFProbe $ffprobe, $width, $height, $colors = [self::DEFAULT_COLOR])
    {
        parent::__construct($audio->getPathfile(), $driver, $ffprobe);
        $this->audio = $audio;
        $this->width = $width;
        $this->height = $height;

        $this->setColors($colors);
    }

    /**
     * @inheritDoc
     *
     * @return WaveformFilters
     */
    public function filters()
    {
        return new WaveformFilters($this);
    }

    /**
     * @inheritDoc
     *
     * @return Waveform
     */
    public function addFilter(WaveformFilterInterface $filter)
    {
        $this->filters->add($filter);

        return $this;
    }

    /**
     * Parameter should be an array containing at least one valid color represented as a HTML color string. For
     * example #FFFFFF or #000000. By default the color is set to black. Keep in mind that if you save the waveform
     * as jpg file, it will appear completely black and to avoid this you can set the waveform color to white (#FFFFFF).
     * Saving waveforms to png is strongly suggested.
     *
     * @param array $colors
     * @return void
     */
    public function setColors(array $colors): void
    {
        foreach ($colors as $row => $value) {
            if (!\preg_match('/^#(?:[0-9a-fA-F]{6})$/', $value)) {
                //invalid color
                //unset($colors[$row]);

                throw new InvalidArgumentException("The provided color '$value' is invalid");
            }
        }

        if (!empty($colors)) {
            $this->colors = $colors;
        }
    }

    /**
     * Returns an array of colors that will be passed to ffmpeg to use for waveform generation. Colors are applied ONLY
     * to the waveform. Background cannot be controlled that easily and it is probably easier to save the waveform
     * as a transparent png file and then add background of choice.
     *
     * @return string[]
     */
    public function getColors(): array
    {
        return $this->colors;
    }

    /**
     * Compiles the selected colors into a string, using a pipe separator.
     *
     * @return string
     */
    protected function compileColors(): string
    {
        return \implode('|', $this->colors);
    }

    /**
     * Saves the waveform in the given filename.
     *
     * @param string  $pathfile
     *
     * @return Waveform
     *
     * @throws RuntimeException
     */
    public function save(string $pathfile)
    {
        /**
         * might be optimized with http://ffmpeg.org/trac/ffmpeg/wiki/Seeking%20with%20FFmpeg
         * @see http://ffmpeg.org/ffmpeg.html#Main-options
         */
        $commands = [
            '-y', '-i', $this->pathfile, '-filter_complex',
            'showwavespic=colors=' . $this->compileColors() . ':s=' . $this->width . 'x' . $this->height,
            '-frames:v', '1',
        ];

        foreach ($this->filters as $filter) {
            $commands = array_merge($commands, $filter->apply($this));
        }

        // add location where the waveform should be saved to
        $commands[] = $pathfile;

        try {
            $this->driver->command($commands);
        } catch (ExecutionFailureException $e) {
            $this->cleanupTemporaryFile($pathfile);
            throw new RuntimeException('Unable to save waveform', $e->getCode(), $e);
        }

        return $this;
    }
}
