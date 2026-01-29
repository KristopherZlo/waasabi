<?php

namespace App\Http\Controllers;

use App\Services\DemoContentService;
use App\Services\UserPayloadService;

class ShowcaseController extends Controller
{
    public function __construct(
        private DemoContentService $demoContent,
        private UserPayloadService $payloadService
    ) {
    }

    public function index()
    {
        return view('showcase', [
            'showcase' => $this->demoContent->showcase(),
            'current_user' => $this->payloadService->currentUserPayload(),
        ]);
    }
}
