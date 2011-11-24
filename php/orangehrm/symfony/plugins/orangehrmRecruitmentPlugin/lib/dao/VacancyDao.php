<?php

/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software; you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation; either
 * version 2 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program;
 * if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor,
 * Boston, MA  02110-1301, USA
 */

/**
 * VacancyDao for CRUD operation
 *
 */
class VacancyDao extends BaseDao {

    /**
     * Retrieve hiring managers list
     * @returns array
     * @throws DaoExceptionf
     */
    public function getHiringManagersList($jobTitle, $vacancyId, $allowedVacancyList=null) {
        try {
            $q = Doctrine_Query::create()
                            ->select('jv.hiringManagerId, e.empNumber, e.firstName, e.middleName, e.lastName')
                            ->from('JobVacancy jv')
                            ->leftJoin('jv.Employee e');
            if ($allowedVacancyList != null) {
                $q->whereIn('jv.id', $allowedVacancyList);
            }
            if (!empty($jobTitle)) {
                $q->addWhere('jv.jobTitleCode = ?', $jobTitle);
            } if (!empty($vacancyId)) {
                $q->addWhere('jv.id = ?', $vacancyId);
            }
            $q->addWhere('e.termination_id IS NULL');
            $results = $q->execute();
            $hiringManagerList = array();
            $newHiringManagerList = array();
            if ($results[0]->gethiringManagerId() != "") {
                foreach ($results as $result) {
                    $hmlist = array('id' => $empNumber = $result->getEmployee()->getEmpNumber(), 'name' => $result->getEmployee()->getFullName());
                    $hiringManagerList[$empNumber] = $hmlist;
                }
                foreach ($hiringManagerList as $hm) {
                    $newHiringManagerList[] = $hm;
                }
            }
            return $newHiringManagerList;
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     * Retrieve vacancy list for a purticular job title
     * @returns doctrine collection
     * @throws DaoException
     */
    public function getVacancyListForJobTitle($jobTitle, $allowedVacancyList, $asArray = false) {
        try {
            $hydrateMode = ($asArray) ? Doctrine :: HYDRATE_ARRAY : Doctrine :: HYDRATE_RECORD;

            $q = Doctrine_Query :: create()
                            ->select('jv.id, jv.name, jv.status')
                            ->from('JobVacancy jv');
            if ($allowedVacancyList != null) {
                $q->whereIn('jv.id', $allowedVacancyList);
            }
            if (!empty($jobTitle)) {
                $q->addWhere('jv.jobTitleCode =?', $jobTitle);
            }
            $q->orderBy('jv.status');
            return $q->execute(array(), $hydrateMode);
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    public function getVacancyListForUserRole($role, $empNumber) {
        try {
            $q = Doctrine_Query :: create()
                            ->select('jv.id')
                            ->from('JobVacancy jv');
            if ($role == HiringManagerUserRoleDecorator::HIRING_MANAGER) {
                $q->where('jv.hiringManagerId = ?', $empNumber);
            }
            if ($role == InterviewerUserRoleDecorator::INTERVIEWER) {
                $q->leftJoin('jv.JobCandidateVacancy jcv')
                        ->leftJoin('jcv.JobInterview ji')
                        ->leftJoin('ji.JobInterviewInterviewer jii')
                        ->where('jii.interviewerId = ?', $empNumber);
            }
            $result = $q->fetchArray();
            $idList = array();
            foreach ($result as $item) {
                $idList[] = $item['id'];
            }
            return $idList;
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     * Retrieve vacancy list
     * @returns doctrine collection
     * @throws DaoException
     */
    public function getAllVacancies($status = "") {
        try {
            $q = Doctrine_Query :: create()
                            ->from('JobVacancy');
            if (!empty($status)) {
                $q->addWhere('status =?', $status);
            }
            return $q->execute();
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     * Get list of vacancies published to web/rss
     * 
     * @return type Array of JobVacancy objects
     * @throws RecruitmentException
     */
    public function getPublishedVacancies() {
        try {
            $q = Doctrine_Query :: create()
                            ->from('JobVacancy')
                            ->where('published_in_feed = ? ', JobVacancy::PUBLISHED)
                            ->andWhere('status = ?', JobVacancy::ACTIVE);
            return $q->execute();
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     * Retrieve vacancy list
     * @returns doctrine collection
     * @throws DaoException
     */
    public function getVacancyList($status=JobVacancy::ACTIVE, $limit=50, $offset=0, $orderBy='definedTime', $order='DESC', $publishedInFeed=JobVacancy::PUBLISHED) {
        try {
            $q = Doctrine_Query :: create()
                            ->from('JobVacancy')
                            ->where('status =?', $status)
                            ->andWhere('publishedInFeed=?', $publishedInFeed)
                            ->orderBy($orderBy . " " . $order)
                            ->offset($offset)
                            ->limit($limit);
            return $q->execute();
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     * Retrieve vacancy list
     * @returns doctrine collection
     * @throws DaoException
     */
    public function saveJobVacancy(JobVacancy $jobVacancy) {
        try {

            if ($jobVacancy->getId() == '') {
                $idGenService = new IDGeneratorService();
                $idGenService->setEntity($jobVacancy);
                $jobVacancy->setId($idGenService->getNextID());
            }

            $jobVacancy->save();
            return true;
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     *
     * @param <type> $srchParams
     * @return <type>
     */
    public function searchVacancies($srchParams) {

        $jobTitle = $srchParams['jobTitle'];
        $jobVacancy = $srchParams['jobVacancy'];
        $hiringManager = $srchParams['hiringManager'];
        $status = $srchParams['status'];
        $orderField = (!empty($srchParams['orderField'])) ? $srchParams['orderField'] : 'v.name';
        $orderBy = (!empty($srchParams['orderBy'])) ? $srchParams['orderBy'] : 'ASC';
        $noOfRecords = $srchParams['noOfRecords'];
        $offset = $srchParams['offset'];

        $sortQuery = "";
        if ($orderField == 'e.emp_firstname') {
            $sortQuery = 'e.emp_firstname ' . $orderBy . ', ' . 'e.emp_lastname ' . $orderBy;
        } else {
            $sortQuery = $orderField . " " . $orderBy;
        }

        $q = Doctrine_Query::create()
                        ->from('JobVacancy v')
                        ->leftJoin('v.Employee e')
                        ->leftJoin('v.JobTitle jt');

        if (!empty($jobTitle)) {
            $q->addwhere('v.jobTitleCode = ?', $jobTitle);
        }
        if (!empty($jobVacancy)) {
            $q->addwhere('v.id = ?', $jobVacancy);
        }
        if (!empty($hiringManager)) {
            $q->addwhere('v.hiringManagerId = ?', $hiringManager);
        }
        if ($status != "") {
            $q->addwhere('v.status = ?', $status);
        }
        $q->orderBy($sortQuery);
        $q->offset($offset);
        $q->limit($noOfRecords);

        $vacancies = $q->execute();
        return $vacancies;
    }

    /**
     *
     * @param <type> $srchParams
     * @return <type>
     */
    public function searchVacanciesCount($srchParams) {

        $jobTitle = $srchParams['jobTitle'];
        $jobVacancy = $srchParams['jobVacancy'];
        $hiringManager = $srchParams['hiringManager'];
        $status = $srchParams['status'];


        $q = Doctrine_Query::create()
                        ->from('JobVacancy v')
                        ->leftJoin('v.Employee e')
                        ->leftJoin('v.JobTitle jt');

        if (!empty($jobTitle)) {
            $q->addwhere('v.jobTitleCode = ?', $jobTitle);
        }
        if (!empty($jobVacancy)) {
            $q->addwhere('v.id = ?', $jobVacancy);
        }
        if (!empty($hiringManager)) {
            $q->addwhere('v.hiringManagerId = ?', $hiringManager);
        }
        if ($status != "") {
            $q->addwhere('v.status = ?', $status);
        }

        $count = $q->execute()->count();
        return $count;
    }

    /**
     * Retrieve vacancy by vacancyId
     * @param int $vacancyId
     * @returns jobVacancy doctrine object
     * @throws DaoException
     */
    public function getVacancyById($vacancyId) {
        try {
            return Doctrine :: getTable('JobVacancy')->find($vacancyId);
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

    /**
     * Delete vacancies
     * @param array $toBeDeletedVacancyIds
     * @return boolean
     */
    public function deleteVacancies($toBeDeletedVacancyIds) {

        $q = Doctrine_Query::create()
                        ->from('JobInterviewInterviewer jii')
                        ->leftJoin('jii.JobInterview ji')
                        ->leftJoin('ji.JobCandidateVacancy jcv')
                        ->leftJoin('jcv.JobVacancy jv')
                        ->whereIn('jv.id', $toBeDeletedVacancyIds);
        $results = $q->execute();
        foreach ($results as $result) {
            $result->delete();
        }

        $qr = Doctrine_Query::create()
                        ->delete()
                        ->from('JobVacancy v')
                        ->whereIn('v.id', $toBeDeletedVacancyIds);

        $noOfAffectedRows = $qr->execute();

        if ($noOfAffectedRows > 0) {
            return true;
        }

        return false;
    }    
    
    /**
     *
     * @param type $empNumber 
     * @return Doctrine_Collection
     */
    public function searchInterviews($empNumber) {
        try {
            $query = Doctrine_Query::create()
                    ->from('JobInterview ji')
                    ->where('ji.JobInterviewInterviewer.interviewerId = ?', $empNumber);
            return $query->execute();
        } catch (Exception $e) {
            throw new DaoException($e->getMessage());
        }
    }

}
