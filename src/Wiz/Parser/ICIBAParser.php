<?php
/**
 * ICIBAParser.php
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author    jack <linjue@wilead.com>
 * @copyright 2007-16/3/28 WIZ TECHNOLOGY
 * @link      http://wizmacau.com
 * @link      http://jacklam.it
 * @link      https://github.com/lamjack
 * @version
 */
namespace Wiz\Parser;

use GuzzleHttp\Client;
use HtmlParser\ParserDom;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Class ICIBAParser
 * @package Wiz\Parser
 */
class ICIBAParser
{
    /**
     * @var string
     */
    const DOMAIN = 'http://www.iciba.com';

    /**
     * @var Client
     */
    private $client;

    /**
     * ICIBAParser constructor.
     */
    public function __construct()
    {
        $this->client = new Client(['cookies' => true]);
    }

    /**
     * @param string $word
     *
     * @return array|bool
     */
    public function query(string $word)
    {
        $reponse = $this->client->request('GET', sprintf('%s/%s', self::DOMAIN, $word), [
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:45.0) Gecko/20100101 Firefox/45.0'
            ],
            'debug' => true
        ]);

        if ($reponse->getStatusCode() === 200) {
            $result = ['word' => $word];
            $this->parser($reponse->getBody()->getContents(), $result);
            return $result;
        }

        return false;
    }

    /**
     * 获取抓取的数据
     *
     * @param string $html
     * @param array  $result
     *
     * @return static|static[]
     */
    protected function parser(string $html, array &$result = [])
    {
        $dom = new ParserDom($html);
        $mainContent = $dom->find('.result-info', 0);

        $this->speak($mainContent, $result);
        $this->rate($mainContent, $result);
        $this->translation($mainContent, $result);
        $this->shapes($mainContent, $result);
        $this->collins($dom, $result);

        return $mainContent;
    }

    /**
     * @param ParserDom $dom
     * @param array     $data
     */
    protected function speak(ParserDom $dom, array &$data)
    {
        $data['speak'] = [];
        /** @var ParserDom $node */
        $node = $dom->find('.info-base', 0);
        if ($node->find('.base-speak')) {
            foreach ($node->find('.base-speak .new-speak-step') as $v) {
                preg_match("/displayAudio\(\'(\S+)\'\)/", $v->getAttr('onmouseover'), $out);
                array_push($data['speak'], trim($out[1]));
            }
        }
    }

    /**
     * @param ParserDom $dom
     * @param array     $data
     */
    protected function rate(ParserDom $dom, array &$data)
    {
        $data['rate'] = 0;
        /** @var ParserDom $node */
        $node = $dom->find('.info-base', 0);
        if ($node->find('.base-word .word-rate')) {
            $data['rate'] = count((array)$node->find('.base-word .word-rate p i.light'));
        }
    }

    /**
     * @param ParserDom $dom
     * @param array     $data
     */
    protected function translation(ParserDom $dom, array &$data)
    {
        $data['translation'] = [];
        /** @var ParserDom $node */
        $node = $dom->find('.info-base', 0);
        if ($node->find('.base-list li span.prop')) {
            /** @var ParserDom $baseListNode */
            $baseListNode = $node->find('.base-list', 0);
            /** @var ParserDom $v */
            foreach ($baseListNode->find('li span.prop') as $v) {
                $data['translation'][] = [str_replace('.', '', trim($v->getPlainText()))];
            }
            foreach ($baseListNode->find('li p') as $k => $v) {
                array_push($data['translation'][$k], str_replace(' ', '', trim($v->getPlainText())));
            }
        }
    }

    /**
     * @param ParserDom $dom
     * @param array     $data
     */
    protected function shapes(ParserDom $dom, array &$data)
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $data['shapes'] = [];
        /** @var ParserDom $node */
        $node = $dom->find('.info-base', 0);
        if ($node->find('li.change p')) {
            /** @var ParserDom $v */
            $k = -1;
            foreach ($node->find('li.change p') as $v) {
                foreach ($v->node->childNodes as $value) {
                    if ($value->nodeName === "span") {
                        $data['shapes'][] = [str_replace('：', '', trim($value->nodeValue))];
                        $k++;
                    }
                    if ($value->nodeName === "a" && $accessor->getValue($data['shapes'][$k], "[1]")) {
                        //归为一类
                        $data['shapes'][$k][1] = $data['shapes'][$k][1] . "," . trim($value->nodeValue);
                    } elseif ($value->nodeName === "a") {
                        array_push($data['shapes'][$k], trim($value->nodeValue));
                    }
                }
            }
        }
    }

    /**
     * @param ParserDom $dom
     * @param array     $data
     */
    protected function collins(ParserDom $dom, array &$data)
    {
        $data['collins'] = [];
        /** @var ParserDom $node */
        $node = $dom->find('.collins-section', 0);
        if ($node) {
            $index = -1;
            $arr = 0;
            //当没有section-h,设定一个default值
            foreach ($node->find('div') as $v) {
                if ($v->getAttr('class') != 'section-h') {
                    $index = 0;
                }
            }
            foreach ($node->find('div') as $v) {
                if ($v->getAttr('class') == 'section-h') {
                    $index++;
                    $arr = 0;
                    preg_match("/<span (\S+)>(.+)<\/span>(.+)/", $v->find('p', 0)->innerHtml(), $out);
                    $data['collins'][$index]['translation']['en'] = trim($out[2]);
                    $data['collins'][$index]['translation']['zh'] = trim($out[3]);
                    //获取sentence有2种情况
                } elseif ((trim($v->getAttr('class')) == 'section-prep' || trim($v->getAttr('class')) == 'section-prep no-order') && $v->find('p.size-chinese .family-english', 0)) {
                    $arr++;
                    $note = $v->find('p.size-chinese .family-english', 0)->getPlainText() . ' ' . $v->find('p.size-chinese .family-chinese', 0)->getPlainText();
                    $note = $note . ' ' . $v->find('p.size-chinese .size-english', 0)->getPlainText();
                    $data['collins'][$index]['translation'][$arr]['note'] = $note;
                } elseif (trim($v->getAttr('class')) == 'text-sentence') {
                    $sentence = array();
                    foreach ($v->find('.sentence-item ') as $value) {
                        preg_match("/(.+)<i class=\"speak-step\" onmouseover=\"displayAudio\(\'(\S+)\'\)\"><\/i>/", $value->find('p.family-english', 0)->innerHtml(), $out);
                        $sentenceZh = $value->find('p.family-chinese', 0)->getPlainText();
                        array_push($sentence, array(
                            'en' => $out[1],
                            'zh' => trim($sentenceZh),
                            'voice' => $out[2]
                        ));
                    }
                    $data['collins'][$index]['translation'][$arr]['sentence'] = $sentence;
                }
            }
        }
    }
}