#!/usr/bin/env php
<?php

/**
 * Prints every Iconify icon the bundle's templates ask for, one `set:name` per line.
 *
 * The bundle ships no icons of its own: `ux_icons.icon_dir` is a single application-level path, so
 * the SVGs live in the installation - in the skeleton's `assets/icons/`. That makes "the dashboard
 * uses a new icon" a change in two repositories, and this script is what tells the second one.
 * tests/skeleton/e2e.sh diffs the output against the skeleton checkout and prints the
 * `ux:icons:import` line to run.
 *
 * It is a backstop, not the proof: the E2E renders every page with `iconify.on_demand` off, so a
 * name nobody committed is a 500 rather than a silent request to api.iconify.design.
 */

$templates = dirname(__DIR__, 2).'/templates';

if (!is_dir($templates)) {
	fwrite(STDERR, "no templates directory at {$templates}\n");
	exit(1);
}

$found   = [];
$dynamic = [];

/** @var SplFileInfo $file */
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templates)) as $file) {
	if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) {
		continue;
	}

	$source = file_get_contents($file->getPathname());

	// <twig:ux:icon name="lucide:bug" .../>
	preg_match_all('/<twig:ux:icon\b[^>]*?\bname="([^"]+)"/', $source, $matches);
	foreach ($matches[1] as $name) {
		if (str_contains($name, '{{')) {
			// the template picks the name at runtime; the literals it picks from are matched below
			$dynamic[$file->getFilename()] = true;
			continue;
		}
		$found[$name] = true;
	}

	// { value: 'dark', label: ..., icon: 'lucide:moon' } - what the runtime names resolve to
	preg_match_all('/\bicon:\s*\'([^\']+)\'/', $source, $matches);
	foreach ($matches[1] as $name) {
		$found[$name] = true;
	}
}

$icons = array_filter(array_keys($found), fn (string $name): bool => (bool) preg_match('/^[a-z0-9-]+:[a-z0-9-]+$/', $name));
sort($icons);

if ($dynamic !== []) {
	fwrite(STDERR, sprintf(
		"note: %s build icon names at runtime; this list covers them through their `icon:` literals\n",
		implode(', ', array_keys($dynamic)),
	));
}

echo implode("\n", $icons), "\n";
