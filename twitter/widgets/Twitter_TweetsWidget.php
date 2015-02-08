<?php

/**
 * Craft Analytics by Dukt
 *
 * @package   Craft Analytics
 * @author    Benjamin David
 * @copyright Copyright (c) 2015, Dukt
 * @license   https://dukt.net/craft/analytics/docs/license
 * @link      https://dukt.net/craft/analytics/
 */

namespace Craft;

class Twitter_TweetsWidget extends BaseWidget
{
    /**
     * @inheritDoc IComponentType::getName()
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('Tweets');
    }

    /**
     * @inheritDoc IWidget::getTitle()
     *
     * @return string
     */
    public function getTitle()
    {
        $settings = $this->getSettings();

        if(!empty($settings->query))
        {
            return Craft::t("{query} tweets", array('query' => $settings->query));
        }

        return Craft::t("Tweets");
    }

    protected function defineSettings()
    {
        return array(
           'query' => array(AttributeType::String),
           'colspan' => array(AttributeType::Number, 'default' => 2),
           'count' => array(AttributeType::Number, 'default' => 10)
        );
    }

    public function getSettingsHtml()
    {
        return craft()->templates->render('twitter/widgets/tweets/settings', array(
           'settings' => $this->getSettings()
        ));
    }

    public function getBodyHtml()
    {
        $settings = $this->getSettings();

        $query = $settings->query;
        $count = $settings->count;

        $params = array('q' => $query, 'count' => $count);

        $response = craft()->twitter->api('get', 'search/tweets', $params);

        $tweets = $response['statuses'];

        $variables = array(
            'tweets' => $tweets
        );

        return craft()->templates->render('twitter/widgets/tweets', $variables);
    }

    public function getColspan()
    {
        $settings = $this->getSettings();

        if(isset($settings->colspan))
        {
            if($settings->colspan > 0)
            {
                return $settings->colspan;
            }
        }

        return 1;
    }
}