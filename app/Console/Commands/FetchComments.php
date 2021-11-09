<?php

namespace App\Console\Commands;

use App\Exports\ReviewsExport;
use Goutte\Client;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\DomCrawler\Crawler;

class FetchComments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'digikala:reviews {url}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get digikala product reviews';

    protected $productId;

    protected $lastPage = 1;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function getProductId()
    {
        $url = parse_url($this->argument('url'));
        $paths = explode('/', $url['path']);
        if (!isset($paths[2])) {
            $this->error('invalid url');
            exit();
        }
        return ltrim($paths[2], 'dkp-');
    }

    public function getReviews($page = 1)
    {
        $client = new Client();

        return $client->request('GET', 'https://www.digikala.com/ajax/product/comments/' . $this->productId . '/?page=' . $page . '&mod=newest_comment');
    }

    public function setLastPage()
    {
        $crawler = $this->getReviews();

        $pagination = $crawler->filter('.c-pager__items > li > a');

        if (count($pagination)) {
            $this->lastPage = $pagination->last()->attr('data-page');
        }
    }

    public function fetchReviews(Crawler $crawler)
    {
        $reviews = collect();
        $reviewsCrawler = $crawler->filter('.c-comments__list .c-comments__item .c-comments__content');
        foreach ($reviewsCrawler as $reviewCrawler) {
            if (!$reviewCrawler->hasChildNodes()) {
                continue;
            }
            $reviews->push([
                'review' => $reviewCrawler->firstChild->textContent,
            ]);
        }

        return $reviews;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->productId = $this->getProductId();
        $this->setLastPage();
        $this->info('product reviews has ' . $this->lastPage . ' page(s)');
        $reviews = collect();
        $this->withProgressBar(range(1, $this->lastPage), function ($page) use (&$reviews) {
            $crawler = $this->getReviews($page);
            $reviews = $reviews->merge($this->fetchReviews($crawler));
        });

        $this->info("\n" . $reviews->count() . ' reviews fetched!');

        Excel::store(new ReviewsExport($reviews), 'reviews-' . $this->productId . '.xlsx');
        return Command::SUCCESS;
    }
}
