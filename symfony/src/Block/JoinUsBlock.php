<?php
namespace App\Block;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractAdminBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Symfony\Component\Templating\EngineInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Validator\ErrorElement;
use Sonata\BlockBundle\Meta\Metadata;
use Doctrine\ORM\EntityManagerInterface;
/**
 * Description of BookingBlock
 *
 * @author H
 */
class JoinUsBlock extends BaseBlockService {

    public function buildEditForm(FormMapper $formMapper, BlockInterface $block) 
    {
        $formMapper;
    }

    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        return $this->renderResponse($blockContext->getTemplate(), array(
            'block'     => $blockContext->getBlock(),
        ), $response);
    }

    public function __construct($name,EngineInterface $templating)
    {
        parent::__construct($name,$templating);
    }

    public function configureSettings(OptionsResolver $resolver) {
        $resolver->setDefaults(array(
            'position' => '1',
            'template' => 'member/joinus_block.html.twig',
        ));
    }
    public function getBlockMetadata($code = null)
    {
        return new Metadata($this->getName(), (null !== $code ? $code : $this->getName()), false, 'messages', [
            'class' => 'fa fa-user',
        ]);
    }

}
