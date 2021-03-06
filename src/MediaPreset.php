<?php

namespace Netflex\Pages;

use JsonSerializable;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;

use Netflex\Support\Accessors;
use Netflex\Pages\Components\Picture;
use Netflex\Pages\Exceptions\BreakpointsMissingException;

/**
 * @property-read string $mode
 * @property-read string $size
 * @property-read string|null $fill
 * @property-read string[] $resolutions
 * @property-read array $breakpoints
 * @property-read int $maxWidth
 */
class MediaPreset implements JsonSerializable
{
  use Accessors;

  /** @var array */
  protected $attributes = [];

  public function __construct($preset = [])
  {
    $this->attributes = $preset;
  }

  /**
   * Registers a preset
   *
   * @param string $name
   * @param static|array $preset
   * @return void
   */
  public static function register($name, $preset)
  {
    if (is_array($preset)) {
      /** @var static */
      $preset = new static($preset);
    }

    Config::set("media.presets.$name", $preset);
  }

  public function getModeAttribute($mode = null)
  {
    if (!$mode || $this->size === '0x0') {
      return Picture::MODE_ORIGINAL;
    }

    return $mode;
  }

  public function getResolutionsAttribute($resolutions)
  {
    $resolutions = $resolutions ?? ['1x', '2x', '3x'];
    return collect($resolutions)
      ->filter(function ($resolution) {
        return is_string($resolution);
      })
      ->map(function ($resolution) {
        return Str::lower($resolution);
      })
      ->filter(function ($resolution) {
        return Str::endsWith($resolution, 'x');
      })
      ->sort(function ($a, $b) {
        return intval($a) - intval($b);
      })
      ->values()
      ->toArray();
  }

  public function setMaxWidthAttribute($maxWidth)
  {
    $this->attributes['maxWidth'] = $maxWidth;
  }

  /**
   * @param array $values
   * @return array
   * @throws BreakpointsMissingException
   */
  public function getBreakpointsAttribute($values = [])
  {
    if ($values && is_array($values)) {
      foreach ($values as $breakpoint => $value) {
        if (is_string($value)) {
          $values[$breakpoint] = $values[$value] ?? null;
        }
      }

      $values = array_filter($values);

      $values = array_map(function ($value) {
        $value['mode'] = $value['mode'] ?? $this->mode;
        $value['size'] = $value['size'] ?? $this->size;
        $value['resolutions'] = $value['resolutions'] ?? $this->resolutions;
        $value['fill'] = $value['fill'] ?? $this->fill;
        return new static($value);
      }, $values);
    }

    $values = $values ?? [];

    $breakpoints = Config::get('media.breakpoints') ?? [];

    if (empty($breakpoints)) {
      throw new BreakpointsMissingException;
    }

    return collect($breakpoints)
      ->mapWithKeys(function ($maxWidth, $breakpoint) use ($values) {
        $value = $values[$breakpoint] ?? new static($this->attributes);
        $value->maxWidth = $value->maxWidth ? $value->maxWidth : $maxWidth;
        return [$breakpoint => $value];
      });
  }

  public function getFillAttribute($fill = null)
  {
    return is_string($fill) ? $fill : null;
  }

  public function getSizeAttribute($size = null)
  {
    $type = gettype($size);
    switch ($type) {
      case 'string':
        if (Str::contains($size, 'x')) {
          return $size;
        }
        return "{$size}x{$size}";
      case 'integer':
      case 'float':
        $size = intval($size);
        return "{$size}x{$size}";
      case 'array':
        $size = array_values(array_filter($size));
        if (count($size) === 1) {
          return "{$size[0]}x{$size[0]}";
        }
        @list($width, $height) = $size;
        $width = $width ?? 0;
        $height = $height ?? 0;
        return "{$width}x{$height}";
      default:
        return '0x0';
    }
  }

  public function getMaxWidthAttribute($maxWidth = 0)
  {
    return (int) $maxWidth;
  }

  public function jsonSerialize()
  {
    return [
      'mode' => $this->mode,
      'size' => $this->size,
      'fill' => $this->fill,
      'maxWidth' => $this->maxWidth,
      'resolutions' => $this->resolutions
    ];
  }
}
