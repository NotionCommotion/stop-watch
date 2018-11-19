<?php
namespace Greenbean\Stopwatch;

class Stopwatch
{
    private $startTime, $times=[], $warnings=[], $useJson, $suppressErrors, $logOnNull;
    public function __construct(array $config=[]) {
        $this->startTime=microtime(true);
        $config=array_merge([
            'useJson'=>true,
            'logOnNull'=>false,
            'suppressErrors'=>false,
            'displayHistory'=>false,
            ],$config);
        $this->useJson=(bool)$config['useJson'];
        $this->suppressErrors=(bool)$config['suppressErrors'];
        $this->logOnNull=(bool)$config['logOnNull'];
        $this->displayHistory=(bool)$config['displayHistory'];
    }
    function __destruct() {
        if($this->times || $this->logOnNull) {
            $currentTime=microtime(true);
            $totalTime=$currentTime-$this->startTime;
            $results=['Total time elapsed is '.$this->convertUnits($totalTime)];
            $totalTimes=[];
            $detail=[];
            $history=[];
            foreach($this->times as $name=>$records) {
                $totalTimes[$name]=0;
                $timeOn=false;
                foreach($records as $record) {
                    if($record['command']=='started' && !$timeOn) {
                        $timeOn=$record['time'];
                    }
                    if($record['command']=='stopped' && $timeOn) {
                        $totalTimes[$name]+=$record['time']-$timeOn;
                        $timeOn=false;
                    }
                    if($this->displayHistory) {
                        $timeChanged=$record['time']-$this->startTime;
                        $timeChangedIndex=(string)$timeChanged;
                        $history[$timeChangedIndex]="$name $record[command] at ".$this->convertUnits($timeChanged, $totalTime);
                    }
                }
                if($record['command']=='started' && $timeOn) {
                    $this->warnings[]="StopWatch ($name) concluded while still running";
                    $totalTimes[$name]+=$currentTime-$timeOn;
                }
            }
            $totalRemainingTime=$totalTime;
            foreach($totalTimes as $name=>$time) {
                $totalRemainingTime-=$time;
                $percent=100*round($time/$totalTime,2);
                $time=$this->convertUnits($time);
                $results[]="Total time for $name was $time ($percent% of total)";
            }
            $percent=100*round($totalRemainingTime/$totalTime,2);
            $totalRemainingTime=$this->convertUnits($totalRemainingTime);
            $results[]="Total time for other processes was $totalRemainingTime ($percent% of total)";
            $results['history']=array_values($history);
            $results['warnings']=$this->warnings;
            syslog(LOG_INFO,$this->useJson?json_encode($results):$this->debugDump($results));
        }
    }

    public function start(string $name){
        if(!isset($this->times[$name])) {
            $this->times[$name]=[];
        }
        elseif($this->times[$name][count($this->times[$name])-1]['command']=='started') {
            $this->warnings[]="Attempt to start Stopwatch ($name) while it is currently running";
            if(!$this->suppressErrors) {
                throw new \Exception($this->warnings[0]);
            }
        }
        $this->times[$name][]=['time'=>microtime(true), 'command'=>'started'];
    }

    public function stop(string $name){
        if(!isset($this->times[$name]) || $this->times[$name][count($this->times[$name])-1]['command']=='stopped') {
            if(!isset($this->times[$name])) {
                $this->warnings[]="Attempt to stop Stopwatch ($name) while not defined";
                $this->times[$name]=[];
            }
            else {
                $this->warnings[]="Attempt to start Stopwatch ($name) while it is currently running";
            }
            if(!$this->suppressErrors) {
                throw new \Exception($this->warnings[0]);
            }
        }
        $this->times[$name][]=['time'=>microtime(true), 'command'=>'stopped'];
    }

    private function convertUnits($t, $tt=null) {
        $tt=$tt?$tt:$t;
        if($tt<0.000001) return ($t*1000000).' microseconds';
        elseif($tt<0.01) return ($t*100).' milliseconds';
        else return $t.' seconds';
    }

    private function debugDump($v) {
        ob_start();
        var_dump($v);
        return ob_get_clean();
    }
}