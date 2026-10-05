<?php

namespace iti\craftitiplugin\controllers;

use Craft;
use craft\web\Controller;
use iti\craftitiplugin\llms\LlmsTxtWriter;
use iti\craftitiplugin\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Only reached when no physical web/llms.txt exists (the web server serves the file
 * directly otherwise) - builds the same content live from Craft data, no API calls.
 */
class LlmsController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionIndex(): Response
    {
        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->enableLlmsTxt) {
            throw new NotFoundHttpException();
        }

        $content = Plugin::getInstance()->llmsTxt->buildContent($settings);

        if ($content === '') {
            throw new NotFoundHttpException();
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->content = LlmsTxtWriter::wrap($content) . "\n";

        return $response;
    }
}
