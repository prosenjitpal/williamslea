<?php
namespace Drupal\gazette_feed\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Render\Markup;

class GazetteFeedController extends ControllerBase {

  protected $httpClient;
  protected $request;

  public function __construct($http_client, RequestStack $request_stack) {
    $this->httpClient = $http_client;
    $this->request = $request_stack->getCurrentRequest();
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('request_stack'),
    );
  }

  public function list() {
        $drupal_page = (int) $this->request->query->get('page', 0);
        $page = $drupal_page + 1;
        $items = [];
    

        try {
            $response = $this->httpClient->get('https://www.thegazette.co.uk/all-notices/notice/data.json', [
            'query' => ['results-page' => $page],
            'verify' => FALSE,
            ]);
            $data = json_decode($response->getBody(), TRUE);

            $total_items = isset($data['f:total']) ? (int) $data['f:total'] : 0;
            /* Pagination data */
            $pagination_links = [];
            if (!empty($data['link'])) {
                foreach ($data['link'] as $link) {
                    if($link['@type']== 'application/json'){
                        $pagination_links[$link['@rel']] = $link['@href'];
                    }
                    
                }
            }

                $currentPage = (int) \Drupal::request()->query->get('page', 1);
                $pageSize = 10;
                $totalItems = (int) $data['f:total'];
                $totalPages = ceil($totalItems / $pageSize);
                $prev = $currentPage -1;
                $pagination_data =['current'=> $currentPage, 'pageSize'=> $pageSize, 
                                    'total_pages' => (int) $totalPages,'prev' => $prev,
                    'base_url' => Url::fromRoute('<current>', [], ['absolute' => FALSE])->toString()];
            /* Pagination data */
            /* listing content */
            if (!empty($data['entry']) && is_array($data['entry'])) {
                foreach ($data['entry'] as $entry) {
                    $title = $entry['title'] ?? 'No title';
                    $title = preg_replace('/^\/n\s*/', '', $title);
                    $link = $entry['link'][1]['@href'] ?? '#';
                    $published = isset($entry['published']) ? date('j F Y', strtotime($entry['published'])) : 'Unknown date';
                    $content = $entry['content'] ?? '';
                    $content = preg_replace('/^\/n\s*/', '', $content);

                    $items[] = [
                        '#type' => 'html_tag',
                        '#tag' => 'article',
                        '#attributes' => ['class' => ['gazette-notice']],
                        'title' => [
                            '#type' => 'html_tag',
                            '#tag' => 'h2',
                            'link' => [
                            '#type' => 'link',
                            '#title' => $title,
                            '#url' => \Drupal\Core\Url::fromUri($link),
                            '#options' => [
                                    'attributes' => [
                                        'target' => '_blank',
                                        'rel' => 'noopener noreferrer', // Recommended for security
                                    ],
                                ],
                            ],
                        ],
                        'date' => [
                            '#markup' => '<p><strong>Published:</strong> ' . $published . '</p>',
                        ],
                        'content' => [
                            '#markup' => '<div>' . \Drupal\Component\Utility\Xss::filter($content) . '</div>',
                        ],
                    ];
                }
            } else {
                 $items[] = ['#markup' => 'No entries found in API response.'];
            }

        } catch (\Exception $e) {
            \Drupal::logger('gazette_feed')->error($e->getMessage());
            $items[] = ['#markup' => 'Error fetching data from The Gazette API.'];
        }

            $build = [
                '#theme' => 'gazette_custom_list',
                '#items' => $items,
                '#pagination' => $pagination_data,
                '#attached' => [
                    'library' => [
                        'gazette_feed/pager_styles',
                    ],
                ],
                '#cache' => ['max-age' => 0],
            ];

            return $build;

    }
    /* listing content */

}
