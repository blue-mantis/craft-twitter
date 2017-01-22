<?php
/**
 * @link      https://dukt.net/craft/twitter/
 * @copyright Copyright (c) 2017, Dukt
 * @license   https://dukt.net/craft/twitter/docs/license
 */

namespace Craft;

/**
 * Twitter Plugin
 */
class TwitterPlugin extends BasePlugin
{
    // Public Methods
    // =========================================================================

    /**
     * Get the plugin’s name
     *
     * @return string The plugin’s name.
     */
    public function getName()
    {
        return Craft::t('Twitter');
    }

    /**
     * Get the plugin’s description
     *
     * @return string The plugin’s description.
     */
    public function getDescription()
    {
        return Craft::t('Tweet field, search widget, embeds, and authenticated Twitter API requests.');
    }

	/**
	 * Get Version
     *
     * @return string
	 */
	public function getVersion()
	{
		return '1.1.1';
	}

    /**
     * Get required plugins.
     *
     * @return array
     */
    public function getRequiredPlugins()
    {
        return array(
            array(
                'name' => "OAuth",
                'handle' => 'oauth',
                'url' => 'https://dukt.net/craft/oauth',
                'version' => '2.0.0'
            )
        );
    }

    /**
     * Get the developer’s name.
     *
     * @return string
     */
    public function getDeveloper()
    {
        return 'Dukt';
    }

    /**
     * Get the developer’s URL.
     *
     * @return string
     */
    public function getDeveloperUrl()
    {
        return 'https://dukt.net/';
    }

    /**
     * Get the Settings URL.
     *
     * @return string
     */
    public function getSettingsUrl()
    {
        return 'twitter/settings';
    }

    /**
     * Get Documentation URL.
     *
     * @return string
     */
    public function getDocumentationUrl()
    {
        return 'https://dukt.net/craft/twitter/docs/';
    }

	/**
	 * Get Release Feed URL.
     *
     * @return string
	 */
	public function getReleaseFeedUrl()
	{
		return 'https://dukt.net/craft/twitter/updates.json';
	}

    /**
     * Hook Register CP Routes.
     *
     * @return array
     */
    public function registerCpRoutes()
    {
        return array(
            'twitter/settings' => array('action' => "twitter/settings/index"),
            'twitter/install' => array('action' => "twitter/install/index"),
        );
    }

    /**
     * Adds support for Twitter user photo resource paths.
     *
     * @param string $path
     *
     * @return string|null
     */
    public function getResourcePath($path)
    {
        // Are they requesting a Twitter user image?
        if (strncmp($path, 'twitteruserimages/', 18) === 0)
        {
            $parts = array_merge(array_filter(explode('/', $path)));

            if (count($parts) != 3)
            {
                return;
            }

            $userId = $parts[1];
            $size = $parts[2];

            $imageSizes = array(
                'mini' => 24,
                'normal' => 48,
                'bigger' => 73,
            );

            if (is_numeric($size) && ($sizeKey = array_search($size, $imageSizes)) !== false)
            {
                $size = $sizeKey;
            }

            $baseUserImagePath = craft()->path->getRuntimePath().'twitter/userimages/'.$userId.'/';
            $sizedFolderPath = $baseUserImagePath.$size.'/';

            // Have we already downloaded this user’s image at this size?
            $contents = IOHelper::getFolderContents($sizedFolderPath, false);

            if ($contents)
            {
                return $contents[0];
            }
            else
            {
                // Do we have the original image?
                if (!is_numeric($size))
                {
                    if ($size == 'original' || array_key_exists($size, $imageSizes))
                    {
                        $sizeName = $size;
                    }
                    else
                    {
                        return;
                    }
                }
                else
                {
                    $sizeName = 'original';

                    foreach ($imageSizes as $sizeKey => $sizeSize)
                    {
                        if ($size <= $sizeSize)
                        {
                            $sizeName = $sizeKey;
                            break;
                        }
                    }
                }

                $originalFolderPath = $baseUserImagePath.$sizeName.'/';

                $contents = IOHelper::getFolderContents($originalFolderPath, false);

                if ($contents)
                {
                    $originalPath = $contents[0];
                }
                else
                {
                    // OK, let’s fetch it then
                    $user = craft()->twitter_api->getUserById($userId);

                    if (!$user || empty($user['profile_image_url_https']))
                    {
                        return;
                    }

                    $url = $user['profile_image_url_https'];

                    if ($sizeName != 'normal')
                    {
                        if ($sizeName == 'original')
                        {
                            $url = str_replace('_normal', '', $url);
                        }
                        else
                        {
                            $url = str_replace('_normal', '_'.$sizeName, $url);
                        }
                    }

                    IOHelper::ensureFolderExists($originalFolderPath);

                    $fileName = pathinfo($url, PATHINFO_BASENAME);
                    $originalPath = $originalFolderPath.$fileName;

                    $response = \Guzzle\Http\StaticClient::get($url, array(
                        'save_to' => $originalPath
                    ));

                    if (!$response->isSuccessful())
                    {
                        return;
                    }
                }

                // If they were actually requesting "mini", "normal", "bigger", or "original", we're done
                if (!is_numeric($size))
                {
                    return $originalPath;
                }

                // Resize it to the requested size
                $fileName = pathinfo($originalPath, PATHINFO_BASENAME);
                $sizedPath = $sizedFolderPath.$fileName;

                IOHelper::ensureFolderExists($sizedFolderPath);

                craft()->images->loadImage($originalPath)
                    ->scaleAndCrop($size, $size)
                    ->saveAs($sizedPath);

                return $sizedPath;
            }
        }
    }

    /**
     * Adds `craft/storage/runtime/twitter/` to the list of things the Clear Caches tool can delete.
     *
     * @return array
     */
    public function registerCachePaths()
    {
        return array(
            craft()->path->getRuntimePath().'twitter/' => Craft::t('Twitter resources'),
        );
    }

    /**
     * Remove all tokens related to this plugin when uninstalled.
     *
     * @return null
     */
    public function onBeforeUninstall()
    {
        if(isset(craft()->oauth))
        {
            craft()->oauth->deleteTokensByPlugin('twitter');
        }
    }

    /**
     * Adds the Twig extension for Twitter.
     *
     * @return TwitterTwigExtension
     */
    public function addTwigExtension()
    {
        Craft::import('plugins.twitter.etc.templating.twigextensions.TwitterTwigExtension');
        return new TwitterTwigExtension();
    }

    // Protected Methods
    // =========================================================================

    /**
     * Defines the settings.
     *
     * @access protected
     *
     * @return array
     */
    protected function defineSettings()
    {
        return array(
            'tokenId' => array(AttributeType::Number),
        );
    }
}
