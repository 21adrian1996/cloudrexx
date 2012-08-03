<?php

namespace Gedmo\Loggable\Entity\Repository;

use Doctrine\Common\Util\Debug as DoctrineDebug;
use Doctrine\ORM\EntityRepository,
    Doctrine\ORM\EntityManager,
    Doctrine\ORM\Mapping\ClassMetadata,
    Doctrine\ORM\Query\Expr;

class LogEntryRepositoryException extends \Exception {};

class CxLogEntryRepository extends \Gedmo\Loggable\Entity\Repository\LogEntryRepository
{
    // Doctrine entity manager
    protected $em = null;
    // Page repository
    protected $pageRepo = null;
    
    /**
     * Constructor
     * 
     * @param  EntityManager  $em
     * @param  ClassMetadata  $class
     */
    public function __construct(EntityManager $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
        $this->em = $em;
        $this->pageRepo = $this->em->getRepository('Cx\Model\ContentManager\Page');
    }
    
    /**
     * Returns an integer with the quantity of log entries with the given action.
     * The log entries are filtered by the page object.
     * 
     * @param   string  $action
     * @return  int     $counter
     */
    public function countLogEntries($action = '')
    {
        $counter = 0;
        $qb = $this->em->createQueryBuilder();
        $sqb = $this->em->createQueryBuilder();
        
        $qb->select('l')
           ->from('Gedmo\Loggable\Entity\LogEntry', 'l')
           ->where('l.action = :action')
           ->andWhere('l.objectClass = :objectClass')
           ->andWhere(
               $qb->expr()->eq(
                   'l.version',
                   '('.$sqb->select('MAX(sl.version) AS version')
                       ->from('Gedmo\Loggable\Entity\LogEntry', 'sl')
                       ->where(
                           $sqb->expr()->eq(
                               'l.objectId',
                               'sl.objectId'
                           )
                       )
                       ->getDQL().')'
               )
           )
           ->setParameter('objectClass', 'Cx\Model\ContentManager\Page');
        
        switch ($action) {
            case 'deleted':
                $qb->setParameter('action', 'remove');
                $logs = $qb->getQuery()->getResult();
                $logsByNodeId = array();
                
                foreach ($logs as $log) {
                    $page = new \Cx\Model\ContentManager\Page();
                    $page->setId($log->getObjectId());
                    $this->revert($page, $log->getVersion() - 1);
                    
                    // Only used to count
                    $logsByNodeId[$page->getNodeIdShadowed()] = 0;
                }
                
                $counter = count($logsByNodeId);
                break;
            case 'unvalidated':
                $qb->orWhere('l.action = :orAction')
                   ->setParameter('action', 'create')
                   ->setParameter('orAction', 'update');
                $logs = $qb->getQuery()->getResult();
                
                foreach ($logs as $log) {
                    $page = $this->pageRepo->findOneById($log->getObjectId());
                    if (!$page) {
                        continue;
                    }
                    
                    if ($page->getEditingStatus() == 'hasDraftWaiting') {
                        $counter++;
                    }
                }
                break;
            default: // create and update
                $where = $action == 'updated' ? 'update' : 'create';
                $qb->setParameter('action', $where);
                $logs = $qb->getQuery()->getResult();
                
                foreach ($logs as $log) {
                    $page = $this->pageRepo->findOneById($log->getObjectId());
                    if (!$page) {
                        continue;
                    }
                    
                    if ($page->getEditingStatus() == '') {
                        $counter++;
                    }
                }
        }
        
        return $counter;
    }
    
    /**
     * Returns an array with the log entries of the given action with a limiter for the paging. It is used for the content workflow overview.
     * The log entries are filtered by the page object.
     * 
     * @param   string  $action
     * @param   int     $offset
     * @param   int     $limit
     * 
     * @return  array   $result
     */
    public function getLogs($action = '', $offset, $limit)
    {
        $result = array();
        
        $qb = $this->em->createQueryBuilder();
        $sqb = $this->em->createQueryBuilder();
        $qb->select('l.objectId, l.action, l.loggedAt, l.version, l.username')
           ->from('Gedmo\Loggable\Entity\LogEntry', 'l')
           ->where('l.action = :action')
           ->andWhere('l.objectClass = :objectClass')
           ->andWhere(
               $qb->expr()->eq(
                   'l.version',
                   '('.$sqb->select('MAX(sl.version) AS version')
                       ->from('Gedmo\Loggable\Entity\LogEntry', 'sl')
                       ->where(
                           $sqb->expr()->eq(
                               'l.objectId',
                               'sl.objectId'
                           )
                       )
                       ->getDQL().')'
               )
           )
           ->orderBy('l.loggedAt', 'DESC')
           ->setParameter('objectClass', 'Cx\Model\ContentManager\Page');
        
        switch ($action) {
            case 'deleted':
                $qb->setParameter('action', 'remove');
                break;
            case 'unvalidated':
                $editingStatus = 'hasDraftWaiting';
                $qb->orWhere('l.action = :orAction')
                   ->setParameter('action', 'create')
                   ->setParameter('orAction', 'update');
                break;
            case 'updated':
                $editingStatus = '';
                $qb->setParameter('action', 'update');
                break;
            default: // create
                $editingStatus = '';
                $qb->setParameter('action', 'create');
        }
        
        switch ($action) {
            case 'deleted':
                $qb->setFirstResult($offset)->setMaxResults($limit);
                $logs = $qb->getQuery()->getResult();
                $logsByNodeId = array();
                
                // Structure the logs by node id and language
                foreach ($logs as $log) {
                    $page = new \Cx\Model\ContentManager\Page();
                    $page->setId($log['objectId']);
                    $this->revert($page, $log['version'] - 1);
                    
                    $result[$page->getNodeIdShadowed()][$page->getLang()] = $log;
                }
                break;
            default: // create, update and unvalidated
                // If setFirstResult() is called, setMaxResult must be also called. Otherwise there is a fatal error.
                // The parameter for setMaxResult() method is a custom value set to 999999, because we need all pages.
                $qb->setFirstResult($offset)->setMaxResults(999999);
                $logs = $qb->getQuery()->getResult();
                $i = 0;
                
                foreach ($logs as $log) {
                    $page = $this->pageRepo->findOneById($log['objectId']);
                    if (!$page) {
                        continue;
                    }
                    
                    if ($page->getEditingStatus() == $editingStatus) {
                        $result[] = $log;
                        $i++;
                    }
                    
                    if ($i >= $limit) {
                        break;
                    }
                }
        }
        
        return $result;
    }
    
    /**
     * Returns an array with the log entries of the given action.
     * The log entries are filtered by the page object.
     * 
     * @param   string  $action
     * @return  array   $result
     */
    public function getLogsByAction($action = '')
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('l')
           ->from('Gedmo\Loggable\Entity\LogEntry', 'l')
           ->where('l.action = :action')
           ->andWhere('l.objectClass = :objectClass')
           ->setParameter('action', $action)
           ->setParameter('objectClass', 'Cx\Model\ContentManager\Page');
        $result = $qb->getQuery()->getResult();
        
        return $result;
    }
    
    /**
     * Returns the latest logs of all pages.
     * The log entries are filtered by the page object.
     * 
     * @return  array  $result
     */
    public function getLatestLogsOfAllPages($fields = null) {
        $result = array();

        $qb = $this->em->createQueryBuilder();
        $sqb = $this->em->createQueryBuilder();
        
        if (is_array($fields)) {
            foreach ($fields as $i=>$field) {
                $fields[$i] = 'l.' . $field;
            }
            $select = implode(', ', $fields);
        } else {
            $select = 'l';
        }
        
        $qb->select($select)
                ->from('Gedmo\Loggable\Entity\LogEntry', 'l')
                ->where($qb->expr()->in('l.id', '' .
                                $sqb->select('sl.id')
                                ->from('Gedmo\Loggable\Entity\LogEntry', 'sl')
                                ->where('sl.objectClass = :objectClass')
                                ->addGroupBy('sl.objectId')
                                ->orderBy('sl.version', 'DESC')
                                ->getDQL() . '')
                )
                ->setParameter('objectClass', 'Cx\Model\ContentManager\Page');

        $logs = $qb->getQuery()->getResult();
       
        if (is_array($logs)) {
            foreach ($logs as $log) {
                if (!is_array($log)) {
                    $result[$log->getObjectId()] = $log;
                } else {
                    $result[$log['objectId']] = $log['username'];
                }
            }
        }

        return $result;
    }

    /**
     * Returns the user name from the given log.
     * 
     * @param   Gedmo\Loggable\Entity\LogEntry
     * @return  string  $username
     */
    public function getUsernameByLog($log)
    {
        if (!is_object($log)) {
            $loggedUser = $log;
        } else {
            $loggedUser = $log->getUsername();
        }
        $user = json_decode($loggedUser);
        $username = $user->{'name'};
        
        return $username;
    }
    
}