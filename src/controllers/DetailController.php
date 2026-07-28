<?php

namespace iti\craftitiplugin\controllers;

use craft\web\Controller;
use iti\craftitiplugin\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class DetailController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionEvent(string $id): Response
    {
        if (!Plugin::getInstance()->getSettings()->enableEvents) {
            throw new NotFoundHttpException();
        }

        return $this->renderTemplate('craft-iti-plugin/event-detail', ['id' => $id]);
    }

    public function actionPlace(string $id): Response
    {
        if (!Plugin::getInstance()->getSettings()->enablePlaces) {
            throw new NotFoundHttpException();
        }

        return $this->renderTemplate('craft-iti-plugin/place-detail', ['id' => $id]);
    }
}
