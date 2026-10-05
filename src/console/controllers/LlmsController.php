<?php

namespace iti\craftitiplugin\console\controllers;

use craft\console\Controller;
use iti\craftitiplugin\Plugin;
use yii\console\ExitCode;

/**
 * Regenerates llms.txt, e.g. after a deploy: `craft craft-iti-plugin/llms/generate`.
 */
class LlmsController extends Controller
{
    public function actionGenerate(): int
    {
        $service = Plugin::getInstance()->llmsTxt;
        $status = $service->regenerate();
        $error = $service->lastError();

        $this->stdout("llms.txt: {$status}\n");

        if ($error !== null) {
            $this->stderr($error . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }
}
