<?php
/**
 * Evidence-backed opportunity suggestions from local task/client rows.
 * These are grounded cues, not confirmed client needs. No invented chat, reviews, or websites.
 *
 * @package wpcable
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Builds opportunity suggestions from imported local records.
 */
class wpcable_opportunities {

	/**
	 * Stale-days threshold for a completed-project follow-up cue.
	 *
	 * @var int
	 */
	const STALE_DAYS = 180;

	/**
	 * @var wpcable_tasks
	 */
	private $tasks;

	/**
	 * @var wpcable_clients
	 */
	private $clients;

	/**
	 * Constructor.
	 *
	 * @param wpcable_tasks   $tasks   Tasks repository.
	 * @param wpcable_clients $clients Clients repository.
	 */
	public function __construct( $tasks = null, $clients = null ) {
		$this->tasks   = $tasks ? $tasks : new wpcable_tasks();
		$this->clients = $clients ? $clients : new wpcable_clients();
	}

	/**
	 * Suggestions across clients, or for one client_id.
	 *
	 * @param int $client_id Optional client filter.
	 * @return array
	 */
	public function get_suggestions( $client_id = 0 ) {
		$groups = $this->clients->get_client_workspace(
			array(
				'client_id' => (int) $client_id,
				'per_page'  => 500,
				'page'      => 1,
			)
		);

		$suggestions = array();
		$clients     = isset( $groups['items'] ) ? $groups['items'] : array();

		foreach ( $clients as $client ) {
			foreach ( $this->suggestions_for_client( $client ) as $item ) {
				$suggestions[] = $item;
			}
		}

		usort(
			$suggestions,
			function ( $a, $b ) {
				$rank = array(
					'high'   => 3,
					'medium' => 2,
					'low'    => 1,
				);
				$ar   = isset( $rank[ $a['confidence'] ] ) ? $rank[ $a['confidence'] ] : 0;
				$br   = isset( $rank[ $b['confidence'] ] ) ? $rank[ $b['confidence'] ] : 0;
				if ( $ar === $br ) {
					return $b['score'] - $a['score'];
				}
				return $br - $ar;
			}
		);

		return $suggestions;
	}

	/**
	 * Build suggestions for one client workspace row (must include task_stats + sample_tasks).
	 *
	 * @param array $client Client workspace row.
	 * @return array
	 */
	public function suggestions_for_client( $client ) {
		if ( empty( $client['client_id'] ) ) {
			return array();
		}

		$stats = isset( $client['task_stats'] ) ? $client['task_stats'] : array();
		$tasks = isset( $client['tasks'] ) ? $client['tasks'] : array();

		$completed = isset( $stats['completed'] ) ? (int) $stats['completed'] : 0;
		$preferred = ! empty( $client['preferred'] ) || ( isset( $stats['preferred'] ) && (int) $stats['preferred'] > 0 );
		$total     = isset( $stats['total'] ) ? (int) $stats['total'] : count( $tasks );
		$notes     = isset( $stats['with_notes'] ) ? (int) $stats['with_notes'] : 0;

		$completed_tasks = array_values(
			array_filter(
				$tasks,
				function ( $task ) {
					return isset( $task['flag'] ) && 'completed' === $task['flag'];
				}
			)
		);

		$latest_completed = $this->latest_activity( $completed_tasks );
		$stale_days       = wpcable_stale_days( $latest_completed );
		$out              = array();

		if ( $completed >= 1 && null !== $stale_days && $stale_days >= self::STALE_DAYS ) {
			$out[] = $this->make_suggestion(
				$client,
				'ebr_checkup',
				__( 'Completed-project follow-up', 'wpcable' ),
				'medium',
				40 + min( 20, $completed * 5 ),
				array(
					sprintf(
						/* translators: 1: completed count, 2: days */
						__( 'Local completed flag on %1$d project(s); last recorded activity is %2$d days ago.', 'wpcable' ),
						$completed,
						$stale_days
					),
					__( 'This is a timing cue from imported activity, not confirmation the client wants an EBR or checkup.', 'wpcable' ),
					__( 'Lost/stale flags are a local heuristic, not proof no other expert was hired.', 'wpcable' ),
				),
				array(
					'completed_count'    => $completed,
					'stale_days'         => $stale_days,
					'latest_activity'    => $latest_completed,
					'source_task_ids'    => $this->ids( $completed_tasks ),
				)
			);
		}

		if ( $completed >= 2 || ( $completed >= 1 && $preferred ) ) {
			$out[] = $this->make_suggestion(
				$client,
				'maintenance_retainer',
				__( 'Repeat-client maintenance cue', 'wpcable' ),
				$completed >= 2 ? 'medium' : 'low',
				30 + min( 25, $completed * 8 ) + ( $preferred ? 10 : 0 ),
				array(
					sprintf(
						/* translators: %d: completed count */
						__( 'This client_id has %d locally completed project(s).', 'wpcable' ),
						$completed
					),
					$preferred
						? __( 'Preferred-contractor flag is set on at least one imported project.', 'wpcable' )
						: __( 'Preferred-contractor flag is not set on imported projects.', 'wpcable' ),
					__( 'Repeat work is inferred from local rows only. Satisfaction and ongoing need are not in this dataset.', 'wpcable' ),
				),
				array(
					'completed_count' => $completed,
					'preferred'       => (bool) $preferred,
					'source_task_ids' => $this->ids( $completed_tasks ),
				)
			);
		}

		if ( $completed >= 1 && $preferred ) {
			$out[] = $this->make_suggestion(
				$client,
				'referral_ask',
				__( 'Referral conversation cue', 'wpcable' ),
				'low',
				20 + min( 15, $completed * 3 ),
				array(
					__( 'Preferred-contractor flag plus at least one locally completed project.', 'wpcable' ),
					__( 'No chat, review, or satisfaction score is imported, so this is not evidence the client would refer.', 'wpcable' ),
				),
				array(
					'completed_count' => $completed,
					'preferred'       => true,
					'source_task_ids' => $this->ids( $completed_tasks ),
				)
			);
		}

		if ( $total >= 3 ) {
			$out[] = $this->make_suggestion(
				$client,
				'resource_augmentation',
				__( 'Multi-project client cue', 'wpcable' ),
				'low',
				15 + min( 20, $total ),
				array(
					sprintf(
						/* translators: %d: project count */
						__( '%d imported projects share this client_id.', 'wpcable' ),
						$total
					),
					__( 'Volume is not a confirmed request for extra help. Full task descriptions are not imported.', 'wpcable' ),
				),
				array(
					'total_tasks'     => $total,
					'source_task_ids' => $this->ids( $tasks ),
				)
			);
		}

		if ( $notes > 0 ) {
			$note_hits = $this->notes_tag_hits( $tasks );
			foreach ( $note_hits as $hit ) {
				$out[] = $this->make_suggestion(
					$client,
					$hit['type'],
					$hit['title'],
					'low',
					12,
					array(
						__( 'Matched a phrase in your local notes (not a Codeable field).', 'wpcable' ),
						$hit['reason'],
						__( 'Local notes are yours; Codeable chat and files were not imported.', 'wpcable' ),
					),
					array(
						'source_task_ids' => $hit['task_ids'],
						'matched_phrase'  => $hit['phrase'],
					)
				);
			}
		}

		return $out;
	}

	/**
	 * Capabilities that are never inferred because the plugin does not import them.
	 *
	 * @return array
	 */
	public static function unsupported_signals() {
		return array(
			array(
				'id'      => 'seo_site_audit',
				'title'   => __( 'SEO / site audit retainer', 'wpcable' ),
				'reason'  => __( 'Client website URL is not imported, so this cue stays unavailable.', 'wpcable' ),
			),
			array(
				'id'      => 'conversation_mining',
				'title'   => __( 'Conversation mining', 'wpcable' ),
				'reason'  => __( 'Workroom chat, files, and full task body are not imported.', 'wpcable' ),
			),
			array(
				'id'      => 'per_task_reviews',
				'title'   => __( 'Per-task reviews / satisfaction', 'wpcable' ),
				'reason'  => __( 'Star ratings on individual projects are not in the local tables.', 'wpcable' ),
			),
			array(
				'id'      => 'global_hire_proof',
				'title'   => __( 'Someone else was hired', 'wpcable' ),
				'reason'  => __( 'The local Lost flag is a heuristic (hidden, canceled, stale, or private workroom). It is not proof another expert was hired.', 'wpcable' ),
			),
			array(
				'id'      => 'auto_outreach',
				'title'   => __( 'Send outreach from this plugin', 'wpcable' ),
				'reason'  => __( 'Codeable access is read-only. Drafts stay on this screen for review; nothing is emailed or posted.', 'wpcable' ),
			),
		);
	}

	/**
	 * Review-only outreach draft from available fields. Never sent.
	 *
	 * @param array $client Client row.
	 * @param array $cue    Optional suggestion.
	 * @return array
	 */
	public function outreach_draft( $client, $cue = array() ) {
		$name  = ! empty( $client['full_name'] ) ? $client['full_name'] : __( 'there', 'wpcable' );
		$title = '';
		if ( ! empty( $cue['evidence']['source_task_ids'][0] ) && ! empty( $client['tasks'] ) ) {
			foreach ( $client['tasks'] as $task ) {
				if ( (int) $task['task_id'] === (int) $cue['evidence']['source_task_ids'][0] ) {
					$title = $task['title'];
					break;
				}
			}
		}

		$subject = sprintf(
			/* translators: %s: client name */
			__( '%s — check-in', 'wpcable' ),
			$name
		);

		$body_lines = array(
			sprintf(
				/* translators: %s: client name */
				__( 'Hi %s,', 'wpcable' ),
				$name
			),
			'',
			$title
				? sprintf(
					/* translators: %s: task title */
					__( 'I was looking back at “%s” in my local records and wanted to see whether anything new has come up.', 'wpcable' ),
					$title
				)
				: __( 'I was looking back at our past Codeable work in my local records and wanted to see whether anything new has come up.', 'wpcable' ),
			'',
			__( 'This draft was generated from imported project fields only. It has not been sent. Chat history and site audits were not available to this plugin.', 'wpcable' ),
		);

		return array(
			'auto_send' => false,
			'subject'   => $subject,
			'body'      => implode( "\n", $body_lines ),
			'notice'    => __( 'Review only — no auto-send, and no Codeable write.', 'wpcable' ),
		);
	}

	/**
	 * Build a suggestion array.
	 *
	 * @param array  $client     Client.
	 * @param string $type       Type slug.
	 * @param string $title      Title.
	 * @param string $confidence high|medium|low.
	 * @param int    $score      Sort score.
	 * @param array  $reasons    Human reasons.
	 * @param array  $evidence   Structured evidence.
	 * @return array
	 */
	private function make_suggestion( $client, $type, $title, $confidence, $score, $reasons, $evidence ) {
		return array(
			'id'             => $type . '-' . (int) $client['client_id'],
			'type'           => $type,
			'title'          => $title,
			'confidence'     => $confidence,
			'score'          => (int) $score,
			'client_id'      => (int) $client['client_id'],
			'client_name'    => isset( $client['full_name'] ) ? $client['full_name'] : '',
			'reasons'        => $reasons,
			'evidence'       => $evidence,
			'disclaimer'     => __( 'Suggested from local records. Not a confirmed client need.', 'wpcable' ),
		);
	}

	/**
	 * Latest last_activity unix time.
	 *
	 * @param array $tasks Tasks.
	 * @return int
	 */
	private function latest_activity( $tasks ) {
		$latest = 0;
		foreach ( $tasks as $task ) {
			$ts = wpcable_normalize_timestamp( isset( $task['last_activity'] ) ? $task['last_activity'] : 0 );
			if ( $ts > $latest ) {
				$latest = $ts;
			}
		}
		return $latest;
	}

	/**
	 * Days since timestamp. 0 if missing.
	 *
	 * @param int $timestamp Unix time.
	 * @return int
	 */
	private function stale_days( $timestamp ) {
		if ( $timestamp <= 0 ) {
			return 0;
		}
		$days = (int) floor( ( time() - $timestamp ) / DAY_IN_SECONDS );
		return max( 0, $days );
	}

	/**
	 * Task IDs.
	 *
	 * @param array $tasks Tasks.
	 * @return array
	 */
	private function ids( $tasks ) {
		$ids = array();
		foreach ( $tasks as $task ) {
			if ( ! empty( $task['task_id'] ) ) {
				$ids[] = (int) $task['task_id'];
			}
		}
		return $ids;
	}

	/**
	 * Optional local-note phrases. Only fires on the expert's own notes.
	 *
	 * @param array $tasks Tasks.
	 * @return array
	 */
	private function notes_tag_hits( $tasks ) {
		$map = array(
			'seo'        => array(
				'type'  => 'seo_from_notes',
				'title' => __( 'SEO note in local notes', 'wpcable' ),
				'words' => array( 'seo', 'search engine' ),
			),
			'automation' => array(
				'type'  => 'automation_from_notes',
				'title' => __( 'Automation note in local notes', 'wpcable' ),
				'words' => array( 'automat', 'ai partner' ),
			),
		);

		$hits = array();
		foreach ( $map as $key => $def ) {
			$matched_ids = array();
			$phrase      = '';
			foreach ( $tasks as $task ) {
				$notes = isset( $task['notes'] ) ? strtolower( (string) $task['notes'] ) : '';
				if ( '' === $notes ) {
					continue;
				}
				foreach ( $def['words'] as $word ) {
					if ( false !== strpos( $notes, $word ) ) {
						$matched_ids[] = (int) $task['task_id'];
						$phrase        = $word;
						break;
					}
				}
			}
			if ( $matched_ids ) {
				$hits[] = array(
					'type'     => $def['type'],
					'title'    => $def['title'],
					'phrase'   => $phrase,
					'task_ids' => $matched_ids,
					'reason'   => sprintf(
						/* translators: %s: matched phrase */
						__( 'Local notes contain “%s”. That is your annotation, not a Codeable classification.', 'wpcable' ),
						$phrase
					),
				);
			}
		}

		return $hits;
	}
}
