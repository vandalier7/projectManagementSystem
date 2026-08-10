<?php

namespace App\Jobs;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

## Downsamples the project's banner and picks a representative accent
## color for the project's UI theme, favoring saturated/vibrant pixels
## over near-white/near-black background so a plain white banner with
## a colorful logo/graphic still yields a usable accent.
class ExtractProjectThemeColor implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	private const FALLBACK_COLOR = '#6366F1'; // adjust to your default accent

	public function __construct(public Project $project)
	{
	}

	public function handle(): void
	{
		if (!$this->project->banner_path) {
			return;
		}

		$binary = Storage::disk('public')->get($this->project->banner_path);

		if ($binary === null) {
			return;
		}

		$color = $this->computeDominantColor($binary);

		$this->project->update(['theme_color' => $color]);
	}

	private function computeDominantColor(string $binary): string
	{
		$src = @imagecreatefromstring($binary);

		if (!$src) {
			return self::FALLBACK_COLOR;
		}

		$srcWidth = imagesx($src);
		$srcHeight = imagesy($src);

		$targetWidth = 50;
		$targetHeight = max(1, (int) round($srcHeight * ($targetWidth / $srcWidth)));

		$thumb = imagecreatetruecolor($targetWidth, $targetHeight);
		imagecopyresampled($thumb, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcWidth, $srcHeight);
		imagedestroy($src);

		$bucketSize = 24;
		$vibrantBuckets = [];
		$allBuckets = [];

		for ($y = 0; $y < $targetHeight; $y++) {
			for ($x = 0; $x < $targetWidth; $x++) {
				$rgb = imagecolorat($thumb, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;

				$lightness = ((max($r, $g, $b) + min($r, $g, $b)) / 2) / 255;
				$key = intdiv($r, $bucketSize) . '-' . intdiv($g, $bucketSize) . '-' . intdiv($b, $bucketSize);

				$this->addToBucket($allBuckets, $key, $r, $g, $b);

				// Skip near-white / near-black pixels for the vibrant pool —
				// plain banner backgrounds shouldn't win by sheer pixel count.
				if ($lightness > 0.92 || $lightness < 0.08) {
					continue;
				}

				$this->addToBucket($vibrantBuckets, $key, $r, $g, $b);
			}
		}

		imagedestroy($thumb);

		// Fall back to the unfiltered pool if the banner is genuinely all
		// white/black/gray and nothing survived the vibrancy filter.
		$pool = !empty($vibrantBuckets) ? $vibrantBuckets : $allBuckets;

		if (empty($pool)) {
			return self::FALLBACK_COLOR;
		}

		usort($pool, fn ($a, $b) => $b['count'] <=> $a['count']);
		$winner = $pool[0];

		return sprintf(
			'#%02X%02X%02X',
			(int) round($winner['r'] / $winner['count']),
			(int) round($winner['g'] / $winner['count']),
			(int) round($winner['b'] / $winner['count'])
		);
	}

	private function addToBucket(array &$buckets, string $key, int $r, int $g, int $b): void
	{
		$buckets[$key] ??= ['count' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
		$buckets[$key]['count']++;
		$buckets[$key]['r'] += $r;
		$buckets[$key]['g'] += $g;
		$buckets[$key]['b'] += $b;
	}
}