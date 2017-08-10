<?php

namespace Entropy\TunkkiBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

class InvoiceeAdmin extends AbstractAdmin
{
    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('name')
            ->add('organization')
            ->add('streetadress')
            ->add('zipcode')
            ->add('city')
            ->add('phone')
            ->add('email')
            ->add('note')
        ;
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->add('name')
            ->add('phone')
            ->add('email')
            ->add('organization')
            ->add('streetadress')
            ->add('zipcode')
            ->add('city')
            ->add('note')
            ->add('_action', null, array(
                'actions' => array(
                    'show' => array(),
                    'edit' => array(),
                    'delete' => array(),
                ),
            ))
        ;
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->add('name')
            ->add('phone')
            ->add('email')
            ->add('organization')
            ->add('streetadress')
            ->add('zipcode')
            ->add('city')
            ->add('note')
        ;
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('name')
            ->add('phone')
            ->add('email')
            ->add('organization')
            ->add('streetadress')
            ->add('zipcode')
            ->add('city')
            ->add('note')
        ;
    }
}
