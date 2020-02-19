<?php

namespace App\PageService;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;
use Sonata\PageBundle\Page\TemplateManager;

class AllEventsPage implements PageServiceInterface
{
    /**
     * @var TemplateManager
     */
    private $templateManager;

    /**
     * @var EntityManager 
     */
    private $em;

    private $name;

    public function __construct($name, TemplateManager $templateManager, $em)
    {
        
        $this->name             = $name;
        $this->templateManager  = $templateManager;
        $this->em               = $em;
    }
    public function getName(){ return $this->name;}

    public function execute(PageInterface $page, Request $request, array $parameters = array(), Response $response = null)
    {
        $events = $this->em->getRepository('App:Event')->findBy(['published' => true]);
        return $this->templateManager->renderResponse(
            $page->getTemplateCode(), 
            array_merge($parameters,array('events'=>$events)), 
            $response
        );
    }
}
