<?php

namespace App\Console\Commands;

use App\Talenta\v1\Request\AuthRequest;
use App\Talenta\v1\Request\LiveAttendanceRequest;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;

class TalentaClockOut extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'talenta:clock-out';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto clock out Talenta.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     * @throws \Exception
     */
    public function handle(): void
    {
        $auth = new AuthRequest();

        $email = env('TALENTA_USER_EMAIL');
        $password = env('TALENTA_USER_PASSWORD');

        $login = $auth->login($email, $password);


        if (!$login) {
            $this->warn(__('LOGIN_FAILED EMAIL: ' . $email));

            return;
        }

        $this->info(__('LOGIN_SUCCESS'));
        // $this->info("$auth->sessionToken");

        $liveAttendance = new LiveAttendanceRequest();
        $liveAttendance->setSessionToken($auth->sessionToken);
        $liveAttendance->setCompanyId(env('TALENTA_COMPANY_ID'));
        $this->info(__('CLOCK_OUT'));
        $this->info(json_encode($liveAttendance->clockOut()));

    }
}
