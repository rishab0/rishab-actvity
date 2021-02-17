<?php

namespace rishab\actvity\controller;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use rishab\actvity\ActivityLog;

class logController extends Controller
{
    public function view($id)
    {
        $activity = ActivityLog::find($id);
        $ip = @$activity->ip_address;

        try {
            $xml = simplexml_load_file("http://www.geoplugin.net/xml.gp?ip=" . $ip);
        } catch (\Exception $ex) {
            $xml = new stdClass();
        }
        $activity['ip_country'] = empty($xml) ?  (string) $xml->geoplugin_countryName : '--';
        $activity['ip_city'] = empty($xml) ?  (string) $xml->geoplugin_city : '--';
        $activity['ip_region'] = empty($xml) ?  (string) $xml->geoplugin_region : '--';
        $activity['ip_lat'] = empty($xml) ?  (string) $xml->geoplugin_latitude : '--';
        $activity['ip_long'] = empty($xml) ?  (string) $xml->geoplugin_longitude : '--';
        return view('log::view', compact('activity'));
    }

    public function logs(Request $request)
    {
        $moduleList = ActivityLog::whereNotNull('model')->select('model')->groupBy('model')->get();
        $statusList = ActivityLog::whereNotNull('status')->select('status')->groupBy('status')->get();
        $createdByList = ActivityLog::whereNotNull('created_by')->select('created_by')->groupBy('created_by')->get();

        $module = $request->module;
        $activityS = $request->activityS;
        $created_at = $request->created_at;
        $activity_by = $request->activity_by;
        $pagination = env('LOG_PAGINATION') ?: 20;
        // return $request;
        $activity = ActivityLog::where(function ($query) use ($module, $activityS, $created_at, $activity_by) {
            if ($module) {
                $query->where('model', 'LIKE', "%{$module}%");
            }
            if ($activityS) {
                $query->where('status', 'LIKE', "%{$activityS}%");
            }
            if ($created_at) {
                $query->where('dateTime', '=', strtotime($created_at));
            }
            if ($activity_by) {
                $query->where('created_by', 'LIKE', "%{$activity_by}%");
            }
        })->latest()->paginate($pagination);
        return view('log::log', compact('activity', 'moduleList', 'statusList', 'createdByList', 'module', 'activityS', 'created_at', 'activity_by'));
    }
    
    public function getLogByPath($path)
    {
        $logs = \File::get($path);
        $logs = explode('#', $logs);
        $system_log = $logs;
        return $system_log;
    }

    public function getLogFileDates()
    {
        $dates = [];
        $files = glob(storage_path('logs/laravel-*.log'));
        $files = array_reverse($files);
        foreach ($files as $path) {
            $fileName = basename($path);
            preg_match('/(?<=laravel-)(.*)(?=.log)/', $fileName, $dtMatch);
            $date = $dtMatch[0];
            array_push($dates, $date);
        }

        return $dates;
    }


    public function systemLog(Request $request)
    {

        $files = glob(storage_path('logs/laravel-*.log'));
        $files = array_reverse($files);
        if ($request->log) {
            $getFirstLog = $request->log;
            $fileName = storage_path('logs/' . 'laravel-' . $getFirstLog . '.log');
        } else {
            $fileName =  $getFirstLog = !empty($files) ? $files[0] : '';
        }

        if (file_exists($fileName)) {
            $pattern = "/^\[(?<date>.*)\]\s(?<env>\w+)\.(?<type>\w+):(?<message>.*)/m";
            $content = file_get_contents($fileName);
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER, 0);

            $logs = [];
            foreach ($matches as $match) {
                $logs[] = [
                    'timestamp' => $match['date'],
                    'env' => $match['env'],
                    'type' => $match['type'],
                    'message' => trim($match['message'])
                ];
            }

            preg_match('/(?<=laravel-)(.*)(?=.log)/', $fileName, $dtMatch);
            $date = $dtMatch[0];
            $availableDates = $this->getLogFileDates();
        }


        $data = [
            'available_log_dates' => @$availableDates,
            'date' => @$date,
            'filename' => @$fileName,
            'logs' => @$logs
        ];
        // return $data;
        return view('log::system_log', compact('data'));
    }

    public function download($date, $filename = null, $headers = [])
    {
        if (is_null($filename)) {
            $filename = "laravel-{$date}.log";
        }

        $path = storage_path('logs/' . 'laravel-' . $date . '.log');

        return response()->download($path, $filename, $headers);
    }
}
