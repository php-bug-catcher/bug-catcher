<?php

namespace BugCatcher\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class HelloController
{
	public function __invoke(Request $request): Response {
		return new Response('Hello BugCatcher!');
	}
}
