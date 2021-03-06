<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2018 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\wiki\helpers;

use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\content\components\ContentContainerController;
use humhub\modules\content\helpers\ContentContainerHelper;
use humhub\modules\wiki\models\WikiPage;
use humhub\modules\wiki\models\WikiPageRevision;
use humhub\modules\wiki\tests\codeception\fixtures\WikiPageFixture;


/**
 * Class Url
 */
class Url extends \yii\helpers\Url
{
    const ROUTE_HOME = '/wiki/overview/index';
    const ROUTE_OVERVIEW = '/wiki/overview/list-categories';
    const ROUTE_WIKI_PAGE = '/wiki/page/view';
    const ROUTE_WIKI_EDIT = '/wiki/page/edit';
    const ROUTE_WIKI_DELETE = '/wiki/page/delete';
    const ROUTE_WIKI_HISTORY = '/wiki/page/history';
    const ROUTE_WIKI_REVERT = '/wiki/page/revert';
    const ROUTE_EXTRACT_TITLES = '/wiki/page/headlines';

    public static function toHome(ContentContainerActiveRecord $container)
    {
        return static::to([static::ROUTE_OVERVIEW, 'container' => $container]);
    }

    public static function toOverview(ContentContainerActiveRecord $container)
    {
        return static::to([static::ROUTE_OVERVIEW, 'container' => $container]);
    }

    public static function toWikiHistory(WikiPage $page)
    {
        return static::to([static::ROUTE_WIKI_HISTORY, 'id' => $page->id, 'container' => $page->content->container]);
    }

    public static function toWikiRevertRevision(WikiPage $page, WikiPageRevision $revision)
    {

        return static::to([static::ROUTE_WIKI_REVERT, 'id' => $page->id, 'toRevision' => $revision->revision, 'container' => $page->content->container]);
    }

    public static function toWikiCreateForCategory(WikiPage $page)
    {
        return static::wikiEdit($page->content->container, null, $page->id);
    }

    public static function toWikiCreate(ContentContainerActiveRecord $container, $categoryId = null)
    {
        return static::wikiEdit($container, null, $categoryId);
    }

    public static function toWikiEdit(WikiPage $page)
    {
        return static::wikiEdit($page->content->container, $page->id);
    }

    private static function wikiEdit(ContentContainerActiveRecord $container, $id = null, $categoryId = null)
    {
        return static::to([static::ROUTE_WIKI_EDIT, 'id' => $id, 'container' => $container, 'categoryId' => $categoryId]);
    }

    /**
     * @param WikiPage $page
     * @param WikiPageRevision $revision
     * @return string
     */
    public static function toWiki(WikiPage $page, WikiPageRevision $revision = null)
    {
        $rev = $revision ? $revision->revision : null;
        return static::to([static::ROUTE_WIKI_PAGE, 'title' => $page->title, 'revision' => $rev, 'container' => $page->content->container]);
    }

    public static function toWikiDelete(WikiPage $page)
    {
        return static::to([static::ROUTE_WIKI_DELETE, 'id' => $page->id, 'container' => $page->content->container]);
    }

    public static function toWikiByTitle($title, ContentContainerActiveRecord $container, WikiPageRevision  $revision = null)
    {
        $rev = $revision ? $revision->revision : null;
        return static::to([static::ROUTE_WIKI_PAGE, 'title' => $title, 'revision' => $rev, 'container' => $container]);
    }

    public static function toExtractTitles()
    {
        return static::to([static::ROUTE_EXTRACT_TITLES, 'container' => ContentContainerHelper::getCurrent()]);
    }

}