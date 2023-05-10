<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class ApoloCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Running Command');

        $fp = fopen('data.csv', 'a+');
        $fp1 = fopen('good-data.csv', 'a+');

        $data = [];

        try {
            for ($i = 600; $i < 2000; $i++) {
                if ($i%100 == 0) {
                    $this->info('Sleep: '.$i);
                    sleep(30);
                }


                $this->info('Page: '.$i);

                $post = [
                    "api_key" => "BQ-ZWIrisKwlqA9qxIktxA",
                    "person_locations" => ["Virginia"],
                    "organization_locations"=> ["Virginia"],
                    "page" => $i,
                    "contact_email_status" => ['verified'],
                    "person_titles" => [
                        "“cfo”", "“ceo”", "“payable”", "“controller”", "“accountant”", "“invoice”", "“payroll”",
                        "“president”", "“owner”", "“chairman”", "“founder”",
                    ],
                    "organization_num_employees_ranges" => [
                        "2001,5000", "201,500", "21,50", "51,100", "101,200", "501,1000", "1001,2000",
                    ],
                    "display_mode" => "explorer_mode",
                    "per_page" => 25,
                    "context" => "people-index-page",
                    "sort_ascending" => false,
                    "sort_by_field" => "sanitized_organization_name_unanalyzed",
                ];

                $response = Http::asJson()
                    ->withHeaders([
                        'Host' => 'api.apollo.io',
                        'Cookie' => 'GCLB=CPbgnb-Rv_L5Qw; X-CSRF-TOKEN=C9zdAkA7zyKRFaE-GXcM2PcA_rhwb96vX9MtynzDIlA881-UxZvieL2k1RKBvtVxM74mnaX8ctOOrHqdeb5CEQ; _leadgenie_session=Rk2w0IH2v95Mv3yhFz3k2oapzl7f55rS4Uvng0rJ3aKb84YkuNHIDyeX5eSH71YRnxWmSZYmPaT8v7FiWXI5R3znTd0iYWDhXJjLzbPt94slj0g7X6AMp1PhpgUjKgcqDeoK6O2ncstt1UbCTJNWEZ8Tyo3pGVaZfwWP3DQxEO8zyag1DXFNs9e9sS7Z3rG3STGY0b6Xi3znaIsNaOokQZ1FYPRAmDV3SOuo6BeFOmHWgb%2B10qNT3CTg%2F9deHP27p0cw3qCMtcvRGTgK1inSouFyVJRwyKZgq10%3D--yQZQLlzbBJ14t%2B8F--8FP2h0Sn%2BPHJKrHlbDtejA%3D%3D',
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
                        'Connection' => 'keep-alive',
                    ])
                    ->post('https://api.apollo.io/v1/mixed_people/search', $post);

                $this->info('Response:'. $response->status());

                if (! $response->ok()) {
                    $this->info('Error:'. $response->body());
                    dump($response->headers());

                    $response->throw();
                }

                $peoples = $response->json('people', []);

                $this->info('Total:'.count($peoples));

                foreach ($peoples as $people) {
                    $email = $people['email'] ?? null;
                    $name = $people['name'] ?? null;
                    $title = $people['title'] ?? '';
                    $companyName = $people['organization']['name'] ?? null;
                    $orgId = $people['organization_id'] ?? null;

                    fputcsv($fp, [
                        $name,
                        $email,
                        $title,
                        $companyName,
                    ]);

                    if ($people['email_status'] !== 'verified') {
                        continue;
                    }

                    if (blank($email) || blank($companyName)) {
                        continue;
                    }

                    $peopleArray = $data[$orgId] ?? [];
                    $good = count($peopleArray) < 3;

                    if ($good) {
                        $ceoRegex = '/ceo|chief executive officer/i';
                        $ceoOtherRegex = '/president|owner|chairman|founder/i';
                        $cfoRegex = '/cfo|chief financial officer/i';
                        $cfoOtherRegex = '/payable|controller|accountant|invoice|payroll/i';

                        if (preg_match($ceoRegex, $title) && ! $this->matchedPreviousTitle($peopleArray, $ceoRegex) && ! $this->matchedPreviousTitle($peopleArray, $ceoOtherRegex)) {
                            $good = true;
                            $this->info('1');
                        } else if (! preg_match($ceoRegex, $title) && ! $this->matchedPreviousTitle($peopleArray, $ceoRegex) && preg_match($ceoOtherRegex, $title) && ! $this->matchedPreviousTitle($peopleArray, $ceoOtherRegex)) {
                            $good = true;
                            $this->info('2');
                        } else if (preg_match($cfoRegex, $title) && ! $this->matchedPreviousTitle($peopleArray, $cfoRegex) && ! $this->matchedPreviousTitle($peopleArray, $cfoOtherRegex)) {
                            $good = true;
                            $this->info('3');
                        } else if (! preg_match($cfoRegex, $title) && ! $this->matchedPreviousTitle($peopleArray, $cfoRegex) && preg_match($cfoOtherRegex, $title) && ! $this->matchedPreviousTitle($peopleArray, $cfoOtherRegex)) {
                            $good = true;
                            $this->info('4');
                        } else {
                            $good = false;
                        }
                    }

                    if ($good) {
                        $data[$orgId][] = [
                            'name' => $name,
                            'email' => $email,
                            'title' => $title,
                            'companyName' => $companyName,
                        ];
                    } else {
                        $this->warn('no matched');
                    }
                }
            }
        } catch(Throwable $e) {
            $this->error($e->getMessage());
        }

        foreach ($data as $people) {
            if (count($people) == 2) {
                fputcsv($fp1, [
                    $people[0]['companyName'],
                    $people[0]['name'],
                    $people[0]['email'],
                    $people[0]['title'],
                    $people[1]['name'],
                    $people[1]['email'],
                    $people[1]['title'],
                ]);
            }
        }
    }

    private function matchedPreviousTitle($data, $match): bool
    {
        if (blank($data)) {
            return false;
        }

        $matched = false;

        foreach ($data as $datum) {
            if (preg_match($match, $datum['title'])) {
                $matched = true;
                break;
            }
        }

        return $matched;
    }
}
