<?php

namespace iti\craftitiplugin\controllers;

use Craft;
use craft\web\Controller;
use iti\craftitiplugin\errors\ContentApiException;
use iti\craftitiplugin\Plugin;
use iti\craftitiplugin\seo\SeoDataFactory;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

class SitemapController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionIndex(): Response
    {
        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->enableEvents && !$settings->enablePlaces) {
            throw new NotFoundHttpException();
        }

        if ($settings->hubname === '') {
            throw new NotFoundHttpException();
        }

        try {
            $xml = Plugin::getInstance()->sitemapApi->fetchSitemapXml($settings->hubname, $settings->apiKey);
        } catch (ContentApiException $e) {
            throw new ServerErrorHttpException('Could not generate sitemap.', 0, $e);
        }

        $xml = SeoDataFactory::rewriteLocTags($xml, Craft::$app->getRequest()->getAbsoluteUrl());

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->content = $xml;

        return $response;
    }
}
