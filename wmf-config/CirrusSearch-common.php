<?php
# WARNING: This file is publically viewable on the web. Do not put private data here.

# This file holds the CirrusSearch configuration which is common to all realms,
# i.e. settings should apply to both the production cluster and the beta
# cluster.
# If you ever want to put in an IP address, you should use the realm-specific
# files CirrusSearch-labs.php and CirrusSearch-production.php

# See: https://wikitech.wikimedia.org/wiki/Search
#
# Contact Wikimedia Operations or Wikimedia Discovery for more details.

$wgSearchType = 'CirrusSearch';

if ( $wmgUseClusterJobqueue ) {
	# The secondary update job has a delay of a few seconds to make sure that Elasticsearch
	# has completed a refresh cycle between when the data that the job needs is added and
	# when the job is run.
	$wgJobTypeConf['cirrusSearchIncomingLinkCount'] = [ 'checkDelay' => true ] +
		$wgJobTypeConf['default'];
}

$wgCirrusSearchElasticQuirks = [];

# Set up the the default cluster to send queries to,
# and the list of clusters to write to.
if ( $wmgCirrusSearchDefaultCluster === 'local' ) {
	$wgCirrusSearchDefaultCluster = $wmfDatacenter;
} else {
	$wgCirrusSearchDefaultCluster = $wmgCirrusSearchDefaultCluster;
}
$wgCirrusSearchWriteClusters = $wmgCirrusSearchWriteClusters;
$wgCirrusSearchClusterOverrides = $wmgCirrusSearchClusterOverrides;
// TODO: remove, transitional config hack to support
// var name change and avoid warnings with interwiki
// (textcat) searches
$wgCirrusSearchFullTextClusterOverrides = $wmgCirrusSearchClusterOverrides;

# Turn off leading wildcard matches, they are a very slow and inefficient query
$wgCirrusSearchAllowLeadingWildcard = false;

# Turn off the more accurate but slower search mode.  It is most helpful when you
# have many small shards.  We don't do that in production and we could use the speed.
$wgCirrusSearchMoreAccurateScoringMode = false;

# Raise the refresh interval to save some CPU at the cost of being slightly less realtime.
$wgCirrusSearchRefreshInterval = 30;

# Limit the number of states generated by wildcard queries (500 will allow about 20 wildcards)
$wgCirrusSearchQueryStringMaxDeterminizedStates = 500;

# Lower the timeouts - the defaults are too high and allow to scan too many
# pages. 40s shard timeout for regex allowed to deep scan 9million pages for
# insource:/the/ on commons. Keep client timeout relatively high in comparaison,
# this is because the shard level timeout is a passive check, i.e. if no doc
# match the check is only done when collecting new segments, and we really
# don't want to timeout the client before the shard retrieval (we may release
# the poolcounter before the end of the query on the backend)
$wgCirrusSearchSearchShardTimeout[ 'regex' ] = '20s';
$wgCirrusSearchClientSideSearchTimeout[ 'regex' ] = 80;
$wgCirrusSearchSearchShardTimeout[ 'default' ] = '10s';
$wgCirrusSearchClientSideSearchTimeout[ 'default' ] = 40;

# Set the backoff for Cirrus' job that reacts to template changes - slow and steady
# will help prevent spikes in Elasticsearch load.
// $wgJobBackoffThrottling['cirrusSearchLinksUpdate'] = 5;  -- disabled, Ori 3-Dec-2015
# Also engage a delay for the Cirrus job that counts incoming links to pages when
# pages are newly linked or unlinked.  Too many link count queries at once could flood
# Elasticsearch.
// $wgJobBackoffThrottling['cirrusSearchIncomingLinkCount'] = 1; -- disabled, Ori 3-Dec-2015

# Ban the hebrew plugin, it is unstable
$wgCirrusSearchBannedPlugins[] = 'elasticsearch-analysis-hebrew';

# Build and use an ngram index for faster regex matching
$wgCirrusSearchWikimediaExtraPlugin = [
	'regex' => [
		'build',
		'use',
		'use_extra_timeout', // More accurate timeout (T152895)
	],
	'super_detect_noop' => true,
	'id_hash_mod_filter' => true,
	'documentVersion' => true,
	'token_count_router' => true,
];

# Enable the "experimental" highlighter on all wikis
$wgCirrusSearchUseExperimentalHighlighter = true;
$wgCirrusSearchOptimizeIndexForExperimentalHighlighter = true;

# Setup the feedback link on Special:Search if enabled
$wgCirrusSearchFeedbackLink = $wmgCirrusSearchFeedbackLink;

# Settings customized per index.
$wgCirrusSearchShardCount = $wmgCirrusSearchShardCount;
$wgCirrusSearchReplicas = $wmgCirrusSearchReplicas;
$wgCirrusSearchMaxShardsPerNode = $wmgCirrusSearchMaxShardsPerNode;
$wgCirrusSearchPreferRecentDefaultDecayPortion = $wmgCirrusSearchPreferRecentDefaultDecayPortion;
$wgCirrusSearchWeights = array_merge( $wgCirrusSearchWeights, $wmgCirrusSearchWeightsOverrides );
$wgCirrusSearchPowerSpecialRandom = $wmgCirrusSearchPowerSpecialRandom;
$wgCirrusSearchAllFields = $wmgCirrusSearchAllFields;
$wgCirrusSearchNamespaceWeights = $wmgCirrusSearchNamespaceWeightOverrides +
	$wgCirrusSearchNamespaceWeights;

$wgCirrusSearchSimilarityProfile = $wmgCirrusSearchSimilarityProfile;
$wgCirrusSearchRescoreProfile = $wmgCirrusSearchRescoreProfile;
$wgCirrusSearchFullTextQueryBuilderProfile = $wmgCirrusSearchFullTextQueryBuilderProfile;
$wgCirrusSearchIgnoreOnWikiBoostTemplates = $wmgCirrusSearchIgnoreOnWikiBoostTemplates;

// We had an incident of filling up the entire clusters redis instances after
// 6 hours, half of that seems reasonable.
$wgCirrusSearchDropDelayedJobsAfter = 60 * 60 * 3;

// Enable cache warming for wikis with more than one shard.  Cache warming is good
// for smoothing out I/O spikes caused by merges at the cost of potentially polluting
// the cache by adding things that won't be used.

// Wikis with more then one shard or with multi-cluster configuration is a
// decent way of saying "wikis we expect will get some search traffic every
// few seconds".  In this commonet the term "cache" refers to all kinds of
// caches: the linux disk cache, Elasticsearch's filter cache, whatever.
if ( isset( $wgCirrusSearchShardCount['eqiad'] ) ) {
	$wgCirrusSearchMainPageCacheWarmer = true;
} else {
	$wgCirrusSearchMainPageCacheWarmer = ( $wgCirrusSearchShardCount['content'] > 1 );
}

// Commons is special
if ( $wgDBname == 'commonswiki' ) {
	$wgCirrusSearchNamespaceMappings[ NS_FILE ] = 'file';
	$wgCirrusSearchReplicaCount['file'] = 2;
} elseif ( $wgDBname == 'officewiki' || $wgDBname == 'foundationwiki' ) {
	// T94856 - makes searching difficult for locally uploaded files
	// T76957 - doesn't make sense to have Commons files on foundationwiki search
} else { // So is everyone else, for using commons
	$wgCirrusSearchExtraIndexes[ NS_FILE ] = [ 'commonswiki_file' ];
	$wgCirrusSearchExtraIndexBoostTemplates = [
		'commonswiki_file' => [
			'wiki' => 'commonswiki',
			'boosts' => [
				// Copied from https://commons.wikimedia.org/wiki/MediaWiki:Cirrussearch-boost-templates
				'Template:Assessments/commons/featured' => 2.5,
				'Template:Picture_of_the_day' => 1.5,
				'Template:Valued_image' => 1.75,
				'Template:Assessments' => 1.5,
				'Template:Quality_image' => 1.75,
			],
		],
	];
}

// Configuration for initial test deployment of inline interwiki search via
// language detection on the search terms.

$wgCirrusSearchLanguageToWikiMap = $wmgCirrusSearchLanguageToWikiMap;

$wgCirrusSearchEnableAltLanguage = $wmgCirrusSearchEnableAltLanguage;
$wgCirrusSearchLanguageDetectors = $wmgCirrusSearchLanguageDetectors;
$wgCirrusSearchTextcatLanguages = $wmgCirrusSearchTextcatLanguages;
$wgCirrusSearchTextcatModel = [ "$IP/vendor/wikimedia/textcat/LM-query", "$IP/vendor/wikimedia/textcat/LM" ];
$wgCirrusSearchTextcatConfig = [
	'maxNgrams' => 9000,
	'maxReturnedLanguages' => 1,
	'resultsRatio' => 1.06,
	'minInputLength' => 3,
	'maxProportion' => 0.85,
	'langBoostScore' => 0.14,
	'numBoostedLangs' => 2,
];

$wgHooks['CirrusSearchMappingConfig'][] = function ( array &$config, $mappingConfigBuilder ) {
	$config['page']['properties']['popularity_score'] = [
		'type' => 'double',
	];
};

// Set the scoring method
$wgCirrusSearchCompletionDefaultScore = 'popqual';

// PoolCounter needs to be adjusted to account for additional latency when default search
// is pointed at a remote datacenter. Currently this makes the assumption that it will either
// be eqiad or codfw which have ~40ms latency between them. Multiples are chosen using
// (p75 + cross dc latency)/p75
if ( $wgCirrusSearchDefaultCluster !== $wmfDatacenter ) {
	// prefix has p75 of ~30ms
	if ( isset( $wgPoolCounterConf[ 'CirrusSearch-Prefix' ] ) ) {
		$wgPoolCounterConf['CirrusSearch-Prefix']['workers'] *= 2;
	}
	// namespace has a p75 of ~15ms
	if ( isset( $wgPoolCounterConf['CirrusSearch-NamespaceLookup' ] ) ) {
		$wgPoolCounterConf['CirrusSearch-NamespaceLookup']['workers'] *= 3;
	}
	// completion has p75 of ~30ms
	if ( isset( $wgPoolCounterConf['CirrusSearch-Completion'] ) ) {
		$wgPoolCounterConf['CirrusSearch-Completion']['workers'] *= 2;
	}
}

// Enable completion suggester
$wgCirrusSearchUseCompletionSuggester = $wmgCirrusSearchUseCompletionSuggester;

// Configure sub-phrases completion
$wgCirrusSearchCompletionSuggesterSubphrases = $wmgCirrusSearchCompletionSuggesterSubphrases;

// Enable phrase suggester (did you mean)
$wgCirrusSearchEnablePhraseSuggest = $wmgCirrusSearchEnablePhraseSuggest;

// Configure ICU Folding
$wgCirrusSearchUseIcuFolding = $wmgCirrusSearchUseIcuFolding;

// Prefer pages in user's language in multilingual wikis
$wgCirrusSearchLanguageWeight = $wmgCirrusSearchLanguageWeight;
// Aliases for filetype: search
$wgCirrusSearchFiletypeAliases = [
	"pdf" => "office",
	"ppt" => "office",
	"doc" => "office",
	"jpg" => "bitmap",
	"image" => "bitmap",
	"webp" => "bitmap",
	"mp3" => "audio",
	"svg" => "drawing"
];

// Activate crossproject search
$wgCirrusSearchEnableCrossProjectSearch = $wmgCirrusSearchEnableCrossProjectSearch;
// Enable the new layout, FIXME: remove the old one
$wgCirrusSearchNewCrossProjectPage = true;
// Display X results per crossproject
$wgCirrusSearchNumCrossProjectSearchResults = 1;
// Control ordering of crossproject searchresults blocks
// Must be a valid profile defined in $wgCirrusSearchCrossProjectBlockScoreProfiles
$wgCirrusSearchCrossProjectOrder = $wmgCirrusSearchCrossProjectOrder;

// Override sister search profiles for specific projects
$wgCirrusSearchCrossProjectProfiles = [
	// full text wikivoyage results are often irrelevant, filter the
	// search with title matches to improve relevance.
	'voy' => [
		'ftbuilder' => 'perfield_builder_title_filter',
		'rescore' => 'wsum_inclinks',
	],
];

$wgCirrusSearchCrossProjectSearchBlackList = $wmgCirrusSearchCrossProjectSearchBlackList;
$wgCirrusSearchCrossProjectShowMultimedia = $wmgCirrusSearchCrossProjectShowMultimedia;

// Configure extra index settings set during index creation
$wgCirrusSearchExtraIndexSettings = $wmgCirrusSearchExtraIndexSettings;

// Limit on the number of tokens we will run phrase rescores with
$wgCirrusSearchMaxPhraseTokens = $wmgCirrusSearchMaxPhraseTokens;

// Enable the search relevance survey where configured
$wgWMESearchRelevancePages = $wmgWMESearchRelevancePages;

if ( $wmgCirrusSearchMLRModel ) {
	// LTR Rescore profile
	$wgCirrusSearchRescoreProfiles['mlr-1024rs'] = [
		'i18n_msg' => 'cirrussearch-qi-profile-wsum-inclinks-pv',
		'supported_namespaces' => 'content',
		'unsupported_syntax' => [ 'full_text_querystring', 'query_string', 'filter_only' ],
		'fallback_profile' => $wmgCirrusSearchMLRModelFallback,
		'rescore' => [
			[
				'window' => 8192,
				'window_size_override' => 'CirrusSearchFunctionRescoreWindowSize',
				'query_weight' => 1.0,
				'rescore_query_weight' => 1.0,
				'score_mode' => 'total',
				'type' => 'function_score',
				'function_chain' => 'wsum_inclinks_pv'
			],
			[
				'window' => 8192,
				'window_size_override' => 'CirrusSearchFunctionRescoreWindowSize',
				'query_weight' => 1.0,
				'rescore_query_weight' => 1.0,
				'score_mode' => 'multiply',
				'type' => 'function_score',
				'function_chain' => 'optional_chain'
			],
			[
				'window' => 1024,
				'query_weight' => 1.0,
				'rescore_query_weight' => 10000.0,
				'score_mode' => 'total',
				'type' => 'ltr',
				'model' => $wmgCirrusSearchMLRModel,
			],
		],
	];

	$wgCirrusSearchUserTesting = $wmgCirrusSearchUserTesting;
}

# Load per realm specific configuration, either:
# - CirrusSearch-labs.php
# - CirrusSearch-production.php
#
require "{$wmfConfigDir}/CirrusSearch-{$wmfRealm}.php";
