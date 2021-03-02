<?php
namespace yii\xhprof\controllers;

use Yii;
use yii\base\ErrorException;
use yii\filters\PageCache;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class DefaultController extends Controller {
	public $layout = 'main';

	const FIELDS = [
		'wt' => ['WallTime', 'microsecs'],
		'cpu' => ['CPU', 'microsecs'],
		'mu' => ['MemUse', 'bytes'],
		'pmu' => ['PeakMemUse', 'bytes'],
	];

	public function behaviors() {
		return [
			[
				'class' => PageCache::class,
				'only' => ['view', 'detail'],
				'duration' => 7*86400,
				'variations' => [
					Yii::$app->language,
					Yii::$app->request->get('id'),
					Yii::$app->request->get('all'),
					Yii::$app->request->get('sort'),
				],
				'enabled' => false,
			],
		];
	}

	public function actionIndex() {
		$module = $this->module;
		$entries = $this->module->gc();
		$data = array_map(function($e) use ($module){
			$res = unserialize(file_get_contents("{$module->dataPath}/$e"));
			unset($res['xhprof']);
			$res['id'] = substr($e, 0, -7);
			return $res;
		}, $entries);
		return $this->render('index', [
			'data' => $data,
			'moduleId' => $module->id,
		]);
	}

	public function actionView($id, $sort = 'wt', $all = 0) {
		$cacheKey = [__CLASS__, 'view', Yii::$app->id, $this->module->id, $id];
		$data = Yii::$app->cache->get($cacheKey);
		if ($data === false) {
			$data = $this->loadXhprof($id);
			$result = [];
			foreach ($data['xhprof'] as $k => $v) {
				if ($k == 'main()') {
					$f = $k;
					$p = '';
				} else {
					list($p, $f) = explode('==>', $k);
				}
				@$result[$f]['ct'] += $v['ct'];
				foreach ($data['keys'] as $key) {
					@$result[$f][$key] += $v[$key];
					@$result[$f][$key . 'e'] += $v[$key];
					@$result[$p][$key . 'e'] -= $v[$key];
				}
			}
			unset($result['']);
			$main = $result['main()'];
			foreach ($result as &$v) {
				$v['ctp'] = $v['ct']/$data['ct']*100;
				foreach ($data['keys'] as $key) {
					$v[$key . 'p'] = $v[$key]/$main[$key]*100;
					$v[$key . 'ep'] = $v[$key . 'e']/$main[$key]*100;
				}
			}
			unset($v);
			$data['xhprof'] = $result;
			Yii::$app->cache->set($cacheKey, $data, 7*86400);
		}
		$label = [
			'fn' => 'Function name',
			'ct' => 'Calls',
			'wt' => 'Incl. Wall Time (microsecs)',
			'wte' => 'Excl. Wall time (microsecs)',
			'cpu' => 'Incl. CPU Time (microsecs)',
			'cpue' => 'Excl. CPU Time (microsecs)',
			'mu' => 'Incl. Memory Used (bytes)',
			'mue' => 'Excl. Memory Used (bytes)',
			'pmu' => 'Incl. Peak Memory Used (bytes)',
			'pmue' => 'Excl. Peak Memory Used (bytes)',
		];
		if (!array_key_exists($sort, $label)) {
			$sort = 'wt';
		}
		if ($sort == 'fn') {
			ksort($data['xhprof']);
		} else {
			uasort($data['xhprof'], function($a, $b) use ($sort) {
				if ($a[$sort] < $b[$sort]) return 1;
				if ($a[$sort] > $b[$sort]) return -1;
				return 0;
			});
		}
		return $this->render('view', [
			'data' => $data,
			'url' => [
				'',
				'id' => $id,
				'all' => $all,
				'sort' => $sort,
			],
			'callgraphUrl' => ['default/callgraph', 'id' => $id],
			'sortLabel' => $label[$sort],
			'fields' => self::FIELDS,
		]);
	}

	public function actionDetail($id, $symbol = null, $sort = 'wt') {
		if (empty($symbol)) {
			return $this->redirect(['view', 'id' => $id, 'sort' => $sort]);
		}
		$cacheKey = [__CLASS__, 'view', Yii::$app->id, $this->module->id, $id, $symbol];
		$data = Yii::$app->cache->get($cacheKey);
		if ($data === false) {
			$data = $this->loadXhprof($id);
			$main = $data['xhprof']['main()'];
			foreach ($data['xhprof'] as $k => $v) {
				if ($k == 'main()') {
					$f = 'main()';
					$p = '';
				} else {
					list($p, $f) = explode('==>', $k);
				}
				if ($f == $symbol) {
					foreach ($v as $kk => $vv) {
						@$result[0][$kk] += $vv;
					}
					if ($p != '') {
						$result[2][$p] = $v;
					} else {
						$result[2] = [];
					}
				} else if ($p == $symbol) {
					$result[3][$f] = $v;
				}
			}
			$result[0]['ctp'] = $result[0]['ct']/$data['ct']*100;
			$result[1]['ct'] = 0;
			$result[1]['ctp'] = 0;
			for ($i = 2; $i < 4; $i++) {
				$total = array_sum(array_column($result[$i], 'ct'));
				foreach ($result[$i] as &$v) {
					$v['ctp'] = $v['ct']/$total*100;
				}
			}
			foreach ($data['keys'] as $k) {
				$result[0][$k . 'p'] = $result[0][$k]/$main[$k]*100;
				$result[1][$k] = $result[0][$k] - array_sum(array_column($result[3], $k));
				$result[1][$k . 'p'] = $result[1][$k]/$result[0][$k]*100;
				$total = array_sum(array_column($result[2], $k));
				foreach ($result[2] as &$v) {
					$v[$k . 'p'] = $v[$k]/$total*100;
				}
				foreach ($result[3] as &$v) {
					$v[$k . 'p'] = $v[$k]/$result[0][$k]*100;
				}
			}
			unset($v);
			$data['xhprof'] = $result;
			Yii::$app->cache->set($cacheKey, $data, 7*86400);
		}
		return $this->render('detail', [
			'id' => $id,
			'symbol' => $symbol,
			'sort' => $sort,
			'data' => $data,
			'fields' => self::FIELDS,
		]);
	}

	public function actionCallgraph($id, $symbol = null, $threshold = 0.01, $critical_path = true) {
		$nodes = $this->getCallgraphData($id, $symbol);
		$threshold = empty($symbol) ? $nodes['main()']['wt']*$threshold : 0;
		$result = "digraph G {\n";
		foreach ($nodes as $k => $node) {
			if ($node['wt'] < $threshold) {
				continue;
			}
			if ($k == 'main()') {
				$shape = "octagon";
				$inc = 'Total';
			} else {
				$shape = "box";
				$inc = 'Inc';
			}
			$label =  sprintf("%s\\n%s: %.3f ms (%.1f%%)\\nExcl: %.3f ms (%.1f%%)\\n%d total calls", addslashes($k), $inc, $node['wt']/1000, $node['wtp'], $node['et']/1000, $node['etp'], $node['ct']);
			if ($node['nt'] > 3) {
				$fill = ' style=filled, fillcolor=red,';
			} else if ($node['nt'] > 2) {
				$fill = ' style=filled, fillcolor=yellow,';
			} else if ($node['nt'] > 1) {
				$fill = ' style=filled, fillcolor=blue,';
			} else if ($k == $symbol) {
				$fill = ' style=filled, fillcolor=orange,';
			} else {
				$fill = '';
			}
			$result .= "\tN{$node['i']}[shape=$shape,$fill label=\"$label\"];\n";
		}
		if ($symbol == null && $critical_path) {
			$this->createGraphEdge($result, $nodes, 'main()', $threshold, $critical_path);
		} else {
			foreach ($nodes as $k => $node) {
				$this->createGraphEdge($result, $nodes, $k, $threshold, false, false);
			}
		}
		$result .= "}\n";
		$proc = proc_open('dot -Tpng', [
			['pipe', 'r'],
			['pipe', 'w'],
			['pipe', 'w'],
		], $pipes);
		if (!is_resource($proc)) {
			return $this->render('callgraph', [
				'error' => 'Can not open process to run dot',
				'script' => $result,
			]);
		}
		fputs($pipes[0], $result);
		fclose($pipes[0]);
		$content = stream_get_contents($pipes[1]);
		if (!empty($error = stream_get_contents($pipes[2]))) {
			return $this->render('callgraph', [
				'error' => $error,
				'script' => $result,
			]);
		}
		Yii::$app->response->format = Response::FORMAT_RAW;
		Yii::$app->response->headers->add('Content-Type', 'image/png');
		return $content;
	}

	private function createGraphEdge(&$output, &$nodes, $symbol, $threshold, $critical, $recursive = true) {
		if (!array_key_exists('child', $nodes[$symbol]) || array_key_exists('finish', $nodes[$symbol])) {
			return;
		}
		$nodes[$symbol]['finish'] = 1;
		$max = $nodes[$symbol]['et'];
		foreach ($nodes[$symbol]['child'] as $k => $v) {
			if ($nodes[$k]['wt'] < $threshold) continue;
			if ($critical && $v['wt'] >= $max) {
				$max = $v['wt'];
				$critical = true;
				$arrowsize = 2;
				$width = 10;
			} else {
				$critical = false;
				$arrowsize = 1;
				$width = 1;
			}
			$output .= sprintf("\tN{$nodes[$symbol]['i']} -> N{$nodes[$k]['i']}[arrowsize=$arrowsize, color=grey, style=\"setlinewidth($width)\", label=\"{$v['ct']} call(s)\", headlabel=\"%.1f%%\", taillabel=\"%.1f%%\"];\n", $v['cp'], $v['pp']);
			if ($recursive) {
				$this->createGraphEdge($output, $nodes, $k, $threshold, $critical, $recursive);
			}
		}
		return;
	}

	private function getCallgraphData($id, $symbol = null) {
		$cacheKey = [__CLASS__, Yii::$app->id, $this->module->id, $id, $symbol];
		$result = Yii::$app->cache->get($cacheKey);
		if ($result !== false) {
//			return $result;
		}
		if (!empty($symbol)) {
			$nodes = $this->getCallgraphData($id);
			$child = $nodes[$symbol]['child'];
			foreach ($nodes as $k => $node) {
				if ($k == $symbol) {
					continue;
				}
				if (array_key_exists($k, $child)) {
					unset($nodes[$k]['child']);
					continue;
				}
				if (!array_key_exists('child', $node) || !array_key_exists($symbol, $node['child'])) {
					unset($nodes[$k]);
					continue;
				}
				$nodes[$k]['child'] = [
					$symbol => $node['child'][$symbol],
				];
			}
			Yii::$app->cache->set($cacheKey, $nodes, 7*86400);
			return $nodes;
		}
		$data = $this->loadXhprof($id);
		$data = $data['xhprof'];
		$n = 0;
		$nodes['main()'] = [
			'i' => $n++,
			'ct' => $data['main()']['ct'],
			'wt' => $data['main()']['wt'],
			'et' => $data['main()']['wt'],
		];
		foreach ($data as $k => $v) {
			if ($k == 'main()') {
				continue;
			}
			list($parent, $child) = explode('==>', $k);
			if (!array_key_exists($parent, $nodes)) {
				$nodes[$parent] = [
					'i' => $n++,
					'ct' => 0,
					'wt' => 0,
					'et' => 0,
				];
			}
			if (!array_key_exists($child, $nodes)) {
				$nodes[$child] = [
					'i' => $n++,
					'ct' => 0,
					'wt' => 0,
					'et' => 0,
				];
			}
			$nodes[$parent]['et'] -= $v['wt'];
			$nodes[$child]['ct'] += $v['ct'];
			$nodes[$child]['wt'] += $v['wt'];
			$nodes[$child]['et'] += $v['wt'];
			$nodes[$parent]['child'][$child] = [
				'ct' => $v['ct'],
				'wt' => $v['wt'],
			];
		}
		$count = 0;
		$sum = 0;
		$ssum = 0;
		$total = $data['main()']['wt'];
		foreach ($nodes as $k => &$node) {
			if ($node['et'] > 0) {
				$count++;
				$x = log($node['et']);
				$sum += $x;
				$ssum += $x*$x;
			}
			$node['wtp'] = $node['wt']/$total*100;
			$node['etp'] = $node['et']/$total*100;
			if (empty($node['child'])) {
				continue;
			}
			foreach ($node['child'] as $kk => &$child) {
				$child['pp'] = $child['wt']/$node['wt']*100;
				$child['cp'] = empty($nodes[$kk]['wt']) ? 0 : $child['wt']/$nodes[$kk]['wt']*100;
			}
			uasort($node['child'], function($x, $y) {
				if ($x['wt'] > $y ['wt']) {
					return -1;
				} else if ($x['wt'] < $y ['wt']) {
					return 1;
				} else {
					return 0;
				}
			});
			unset($child);
		}
		unset($node);
		$avg = $sum/$count;
		$sigma = sqrt($ssum/$count - $avg*$avg);
		array_walk($nodes, function(&$node) use ($avg, $sigma) {
			$node['nt'] = (log($node['et']) - $avg)/$sigma;
		});
		Yii::$app->cache->set($cacheKey, $nodes, 7*86400);
		return $nodes;
	}

	private function loadXhprof($id) {
		$fname = "{$this->module->dataPath}/$id.xhprof";
		try {
			$data = unserialize(file_get_contents("{$this->module->dataPath}/$id.xhprof"));
		} catch (ErrorException $e) {
			throw new NotFoundHttpException($e->getMessage());
		}
		$data['keys'] = array_intersect(['wt', 'cpu', 'mu', 'pmu'], array_keys($data['xhprof']['main()']));
		$data['ct'] = array_sum(array_column($data['xhprof'], 'ct'));
		return $data;
	}

}
