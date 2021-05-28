<?php

namespace FroshWebP\Components;

use Doctrine\DBAL\Connection;
use Enlight_Event_Exception;
use Exception;
use PDO;
use Shopware\Bundle\MediaBundle\MediaServiceInterface;
use Shopware\Components\Theme;
use Shopware\Components\Theme\Inheritance as InheritanceCore;
use Shopware\Models\Shop;

/**
 * Class Inheritance
 */
class Inheritance extends InheritanceCore
{
    /**
     * @var Inheritance
     */
    private $inheritance;
    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var MediaServiceInterface
     */
    private $mediaService;

    /**
     * Inheritance constructor.
     *
     * @param InheritanceCore       $inheritance
     * @param Connection            $connection
     * @param MediaServiceInterface $mediaService
     */
    public function __construct(InheritanceCore $inheritance, Connection $connection, MediaServiceInterface $mediaService)
    {
        $this->inheritance = $inheritance;
        $this->connection = $connection;
        $this->mediaService = $mediaService;
    }

    /**
     * @param Shop\Template $template
     *
     * @throws Exception
     *
     * @return array|mixed
     */
    public function buildInheritances(Shop\Template $template)
    {
        return $this->inheritance->buildInheritances($template);
    }

    /**
     * @param Shop\Template $template
     *
     * @return mixed|string[]
     */
    public function getInheritancePath(Shop\Template $template)
    {
        return $this->inheritance->getInheritancePath($template);
    }

    /**
     * @param Shop\Template $template
     * @param Shop\Shop     $shop
     * @param bool          $lessCompatible
     *
     * @throws Enlight_Event_Exception
     *
     * @return array
     */
    public function buildConfig(Shop\Template $template, Shop\Shop $shop, $lessCompatible = true)
    {
        $config = $this->inheritance->buildConfig($template, $shop, $lessCompatible);

        if (!$lessCompatible) {
            $config = array_merge($config, $this->getWebpLogos($template, $shop));
        }

        return $config;
    }

    /**
     * @param Shop\Template $template
     *
     * @throws Enlight_Event_Exception
     *
     * @return array|mixed
     */
    public function getTemplateDirectories(Shop\Template $template)
    {
        return $this->inheritance->getTemplateDirectories($template);
    }

    /**
     * @param Shop\Template $template
     *
     * @throws Enlight_Event_Exception
     *
     * @return array|mixed
     */
    public function getSmartyDirectories(Shop\Template $template)
    {
        return $this->inheritance->getSmartyDirectories($template);
    }

    /**
     * @param Shop\Template $template
     *
     * @throws Exception
     *
     * @return mixed|string[]
     */
    public function getTemplateCssFiles(Shop\Template $template)
    {
        return $this->inheritance->getTemplateCssFiles($template);
    }

    /**
     * @param Shop\Template $template
     *
     * @throws Exception
     *
     * @return mixed|string[]
     */
    public function getTemplateJavascriptFiles(Shop\Template $template)
    {
        return $this->inheritance->getTemplateJavascriptFiles($template);
    }

    /**
     * @param Shop\Template $template
     *
     * @throws Exception
     *
     * @return mixed|Theme
     */
    public function getTheme(Shop\Template $template)
    {
        return $this->inheritance->getTheme($template);
    }

    /**
     * @param Shop\Template $template
     * @param Shop\Shop     $shop
     *
     * @return array
     */
    private function getWebpLogos(Shop\Template $template, Shop\Shop $shop): array
    {
        $logos = $this->connection->createQueryBuilder()
            ->addSelect('element.name')
            ->addSelect('eValue.value')
            ->from('s_core_templates_config_elements', 'element')
            ->innerJoin('element', 's_core_templates_config_values', 'eValue', 'eValue.element_id = element.id')
            ->andWhere('name IN (:names)')
            ->andWhere('eValue.shop_id = :shopId')
            ->andWhere('element.template_id = :templateId')
            ->setParameter('names', [
                'mobileLogo',
                'tabletLogo',
                'tabletLandscapeLogo',
                'desktopLogo',
            ], Connection::PARAM_STR_ARRAY)
            ->setParameter('templateId', $template->getId())
            ->setParameter('shopId', $shop->getMain() ? $shop->getMain()->getId() : $shop->getId())
            ->execute()
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        $result = [];
        foreach ($logos as $key => $value) {
            $value = unserialize($value);
            $ext = pathinfo($value, PATHINFO_EXTENSION);

            if (strtolower($ext) === 'svg') {
                continue;
            }

            $value = str_replace('.' . $ext, '.webp', $value);

            $result[$key . 'Webp'] = $this->mediaService->getUrl($value);
        }

        return $result;
    }
}
