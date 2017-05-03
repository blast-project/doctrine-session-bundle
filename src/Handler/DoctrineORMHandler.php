<?php

/*
 * Credits to ShapeCode for this handler https://github.com/shapecode
 */

namespace Blast\DoctrineSessionBundle\Handler;

use Blast\DoctrineSessionBundle\Entity\Session;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * Class DoctrineORMHandler
 */
class DoctrineORMHandler implements \SessionHandlerInterface
{
    /**
     * @var EntityManagerInterface 
     */
    protected $entityManager;
    
    /**
     *
     * @var EntityRepository
     */
    protected $repository;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(ManagerRegistry $managerRegistry, $sessionClass)
    {
        $this->entityManager = $managerRegistry->getManagerForClass($sessionClass);
        $this->repository = $this->entityManager->getRepository($sessionClass);
    }

    /**
     * @inheritDoc
     */
    public function close()
    {   
        return true;
    }

    /**
     * @inheritDoc
     */
    public function destroy($sessionId)
    {
        $qb = $this->repository->createQueryBuilder('s');
         
        return $qb
            ->delete()
            ->where($qb->expr()->eq('s.sessionId', ':sessionId'))
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->execute()
        ;
    }
    
    /**
     * @inheritDoc
     */
    public function gc($maxLifetime)
    {
        $qb = $this->repository->createQueryBuilder('s');
            
        $qb->delete()
            ->where($qb->expr()->lt('s.expiresAt', ':now'))
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute()
        ;
        
        return true;
    }

    /**
     * @inheritDoc
     */
    public function open($savePath, $sessionId)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function read($sessionId)
    {
        $session = $this->getSession($sessionId);
        
        if ( !$session || is_null($session->getData()) )
            return '';
        
        $resource = $session->getData();
        
        return is_resource($resource) ? stream_get_contents($resource) : $resource;
    }

    /**
     * @inheritDoc
     */
    public function write($sessionId, $sessionData)
    {
        $maxLifetime = (int) ini_get('session.gc_maxlifetime');
        $session = $this->getSession($sessionId);
        $expiry = new \DateTime();
        
        $expiry->add(new \DateInterval('PT' . $maxLifetime . 'S'));

        $session->setData($sessionData);
        $session->setExpiresAt($expiry);

        $this->entityManager->persist($session);
        $this->entityManager->flush($session);
        
        return true;
    }

    /**
     * @param $sessionId
     *
     * @return Session
     */
    protected function getNewInstance($sessionId)
    {
        $className = $this->repository->getClassName();
        $session = new $className();
        
        $session->setSessionId($sessionId);
        
        return $session;
    }

    /**
     * @param $sessionId
     *
     * @return Session
     */
    protected function getSession($sessionId)
    {
        $session = $this->repository->findOneBy([
            'sessionId' => $sessionId
        ]);
        
        if ( !$session )
            $session = $this->getNewInstance($sessionId);
        
        return $session;
    }

}