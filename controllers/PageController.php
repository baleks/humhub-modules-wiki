<?php

namespace humhub\modules\wiki\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\content\models\Content;
use humhub\modules\file\models\File;
use humhub\modules\space\models\Space;
use humhub\modules\wiki\helpers\Url;
use humhub\modules\wiki\models\forms\WikiPageItemDrop;
use humhub\modules\wiki\models\WikiPage;
use humhub\modules\wiki\models\WikiPageRevision;
use humhub\widgets\MarkdownView;
use Yii;
use yii\base\Exception;
use yii\web\HttpException;

/**
 * PageController
 *
 * @author luke
 */
class PageController extends BaseController
{

    /**
     * @param $action
     * @return bool
     * @throws HttpException
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            if ($this->contentContainer instanceof Space && !$this->contentContainer->isMember()) {
                throw new HttpException(403, Yii::t('WikiModule.base', 'You need to be member of the space "%space_name%" to access this wiki page!', ['%space_name%' => $this->contentContainer->name]));
            }
            return true;
        }

        return false;
    }

    /**
     * @return $this|void|\yii\web\Response
     * @throws \yii\base\Exception
     */
    public function actionIndex()
    {
        return $this->redirect($this->contentContainer->createUrl('/wiki/overview'));
    }

    /**
     * @return string
     * @throws \yii\base\Exception
     */
    public function actionList()
    {
        return $this->redirect($this->contentContainer->createUrl('/wiki/overview/list-categories'));
    }

    /**
     * @return $this|string|void|\yii\web\Response
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\StaleObjectException
     */
    public function actionView()
    {
        $title = Yii::$app->request->get('title');
        $revisionId = Yii::$app->request->get('revision', 0);

        $page = WikiPage::find()->contentContainer($this->contentContainer)->where(['title' => $title])->one();
        if ($page !== null) {

            $revision = null;
            if ($revisionId != 0) {
                $revision = WikiPageRevision::findOne(['wiki_page_id' => $page->id, 'revision' => $revisionId]);
            }
            if ($revision == null) {
                $revision = $page->latestRevision;

                // There is no revision for this page.
                if ($revision == null) {

                    // Delete page without revision
                    $page->delete();

                    // Forward to edit
                    return $this->redirect($this->contentContainer->createUrl('edit', array('title' => $page->title)));
                }
            }

            return $this->render('view', [
                'page' => $page,
                'revision' => $revision,
                'homePage' => $this->getHomePage(),
                'contentContainer' => $this->contentContainer,
                'content' => $revision->content,
                'canViewHistory' => $this->canViewHistory(),
                'canEdit' => $this->canEdit($page),
                'canAdminister' => $this->canAdminister(),
                'canCreatePage' => $this->canCreatePage()
            ]);
        } else {
            return $this->redirect($this->contentContainer->createUrl('edit', array('title' => $title)));
        }
    }

    /**
     * @return $this|string|void|\yii\web\Response
     * @throws HttpException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function actionEdit($id = null, $title = null, $categoryId = null)
    {
        $page = WikiPage::find()->contentContainer($this->contentContainer)->readable()->where(['wiki_page.id' => $id])->one();

        if ($page === null) {
            if (!$this->canCreatePage()) {
                throw new HttpException(403, Yii::t('WikiModule.base', 'Page creation disabled!'));
            }

            $page = new WikiPage($this->contentContainer, ['title' => $title, 'scenario' => WikiPage::SCENARIO_CREATE]);
        } elseif (!$this->canEdit($page)) {
            throw new HttpException(403, Yii::t('WikiModule.base', 'Page not editable!'));
        }

        if ($this->canAdminister()) {
            $page->scenario = 'admin';
        }

        if($categoryId) {
            $category = WikiPage::find()->contentContainer($this->contentContainer)->readable()->where(['wiki_page.id' => $categoryId, 'is_category' => 1])->one();
            if($category) {
                $page->parent_page_id = $categoryId;
            }
        }

        $revision = $page->createRevision();

        if ($page->load(Yii::$app->request->post()) && $revision->load(Yii::$app->request->post())) {
            $page->content->container = $this->contentContainer;
            if ($page->save()) {
                $page->fileManager->attach(Yii::$app->request->post('fileList'));

                $revision->wiki_page_id = $page->id;
                if ($revision->save()) {
                    return $this->redirect($this->contentContainer->createUrl('view', ['title' => $page->title]));
                }
            }
        }

        return $this->render('edit', [
            'page' => $page,
            'revision' => $revision,
            'homePage' => $this->getHomePage(),
            'contentContainer' => $this->contentContainer,
            'canAdminister' => $this->canAdminister(),
            'hasCategories' => $this->hasCategoryPages()
        ]);
    }

    public function actionSort()
    {
        $dropModel = new WikiPageItemDrop(['contentContainer' => $this->contentContainer]);
        if($dropModel->load(Yii::$app->request->post()) && $dropModel->save()) {
            return $this->asJson(['success' => true]);
        }

        return $this->asJson(['success' => false]);
    }

    /**
     * @return string
     * @throws HttpException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function actionHistory()
    {
        if (!$this->canViewHistory()) {
            throw new HttpException(403, Yii::t('WikiModule.base', 'Permission denied. You have no rights to view the history.'));
        }

        $id = Yii::$app->request->get('id');

        $page = WikiPage::find()->contentContainer($this->contentContainer)->readable()->where(['wiki_page.id' => $id])->one();

        if ($page === null) {
            throw new HttpException(404, Yii::t('WikiModule.base', 'Page not found.'));
        }

        $query = WikiPageRevision::find();
        $query->orderBy('wiki_page_revision.id DESC');
        $query->where(['wiki_page_id' => $page->id]);
        $query->joinWith('author');

        $countQuery = clone $query;

        $pagination = new \yii\data\Pagination(['totalCount' => $countQuery->count(), 'pageSize' => "20"]);
        $query->offset($pagination->offset)->limit($pagination->limit);


        return $this->render('history', array(
                'page' => $page,
                'revisions' => $query->all(),
                'pagination' => $pagination,
                'homePage' => $this->getHomePage(),
                'contentContainer' => $this->contentContainer)
        );
    }

    /**
     * @return $this|void|\yii\web\Response
     * @throws HttpException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\StaleObjectException
     */
    public function actionDelete($id)
    {
        $this->forcePostRequest();

        $page = WikiPage::find()->contentContainer($this->contentContainer)->where(['wiki_page.id' => $id])->one();

        if (!$page) {
            throw new HttpException(404, Yii::t('WikiModule.base', 'Page not found.'));
        }

        if (!$this->canAdminister()) {
            throw new HttpException(403, Yii::t('WikiModule.base', 'Permission denied. You have no administration rights.'));
        }

        $page->delete();

        return $this->redirect($this->contentContainer->createUrl('index'));
    }

    /**
     * @return $this|void|\yii\web\Response
     * @throws HttpException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function actionRevert()
    {
        $this->forcePostRequest();

        $id = (int)Yii::$app->request->get('id');
        $toRevision = (int)Yii::$app->request->get('toRevision');

        $page = WikiPage::find()->contentContainer($this->contentContainer)->readable()->where(['wiki_page.id' => $id])->one();

        if ($page === null) {
            throw new HttpException(404, Yii::t('WikiModule.base', 'Page not found.'));
        }

        if (!$this->canEdit($page)) {
            throw new HttpException(403, Yii::t('WikiModule.base', 'Page not editable!'));
        }

        $revision = WikiPageRevision::findOne(array(
            'revision' => $toRevision,
            'wiki_page_id' => $page->id
        ));

        if ($revision->is_latest) {
            throw new HttpException(404, Yii::t('WikiModule.base', 'Revert not possible. Already latest revision!'));
        }

        $revertedRevision = $page->createRevision();
        $revertedRevision->content = $revision->content;
        $revertedRevision->save();

        return $this->redirect(Url::toWiki($page));

        return ['success' => true, 'redirect' => Url::toWiki($page)];
    }

    /**
     * Markdown preview action for MarkdownViewWidget
     * We require an own preview action here to also handle internal wiki links.
     * @throws HttpException
     * @throws \Exception
     */
    public function actionPreviewMarkdown()
    {
        $this->forcePostRequest();
        $content = MarkdownView::widget(['markdown' => Yii::$app->request->post('markdown'), 'parserClass' => 'humhub\modules\wiki\Markdown']);

        return $this->renderAjaxContent($content);
    }

    /**
     * @param WikiPage $page
     * @return boolean can edit given wiki site?
     * @throws \yii\base\InvalidConfigException
     */
    public function canEdit($page)
    {
        return $page->content->canEdit();
    }

    public function actionSearch($term = null) {
        return $this->asJson([
            ['label' => 'Test1', 'value' => 1],
            ['label' => 'Test2', 'value' => 2],
            ['label' => 'Test3', 'value' => 3],
            ['label' => 'Test4', 'value' => 4],
        ]);
    }




}
