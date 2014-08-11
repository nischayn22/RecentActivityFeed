RecentActivityFeed
==================

A customizable way to view recent activities on your wiki. 

Currently supports customized action, title for the display and activity users for the query.


Usage
=====

An example from Education Program's view course activity action:

                $recentActivityFeed = new \SpecialRecentActivityFeed( 'Education Program:' . $courseTitle );
                $recentActivityFeed->setCustomDescription( 'Activity for course ' . $courseTitle );
                $recentActivityFeed->setParams(array('action' => 'epcourseactivity'));

                $students = $course->getStudents();
                $users = array();
                foreach($students as $student){
                  $users[] = $student->getUser()->getId();
                }

                $recentActivityFeed->setAdditionalConds( array('rc_user' => $users ) );
                $recentActivityFeed->execute('');
                return;



Authors
========
Nischay Nahata for Wikiworks.com for wikiedu.org
