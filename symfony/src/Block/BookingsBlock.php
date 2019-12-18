<?php
namespace App\Block;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Validator\ErrorElement;
use App\Entity\Booking;
use Doctrine\ORM\EntityManager;
/**
 * Description of BookingBlock
 *
 * @author H
 */
class BookingsBlock extends BaseBlockService {

    protected $em;

    public function buildEditForm(FormMapper $formMapper, BlockInterface $block) 
    {
        $formMapper;
    }

    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        $bookings = $this->em->getRepository(Booking::class)->findBy([
            'itemsReturned' => false, 
            'cancelled' => false
        ],[
            'bookingDate' => 'DESC'
        ]);
         
        return $this->renderResponse($blockContext->getTemplate(), array(
            'block'     => $blockContext->getBlock(),
            'bookings'  => $bookings
        ), $response);
    }

    public function __construct($name,$templating, EntityManager $em)
    {
        $this->em = $em;
        parent::__construct($name,$templating);
    }

    public function configureSettings(OptionsResolver $resolver) {
        $resolver->setDefaults(array(
            'position' => '1',
            'template' => 'block/bookings.html.twig',
        ));
    }
}
