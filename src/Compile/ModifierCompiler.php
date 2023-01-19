<?php
/**
 * Smarty Internal Plugin Compile Modifier
 * Compiles code for modifier execution
 *


 * @author     Uwe Tews
 */

namespace Smarty\Compile;

use Smarty\Compile\Base;

/**
 * Smarty Internal Plugin Compile Modifier Class
 *


 */
class ModifierCompiler extends Base {

	/**
	 * Compiles code for modifier execution
	 *
	 * @param array $args array with attributes from parser
	 * @param \Smarty\Compiler\Template $compiler compiler object
	 * @param array $parameter array with compilation parameter
	 *
	 * @return string compiled code
	 * @throws \Smarty\CompilerException
	 * @throws \Smarty\Exception
	 */
	public function compile($args, \Smarty\Compiler\Template $compiler, $parameter = [], $tag = null, $function = null) {

		$compiler->has_code = true;

		// check and get attributes
		$_attr = $this->getAttributes($compiler, $args);
		$output = $parameter['value'];
		// loop over list of modifiers
		foreach ($parameter['modifierlist'] as $single_modifier) {
			/* @var string $modifier */
			$modifier = $single_modifier[0];
			$single_modifier[0] = $output;
			$params = implode(',', $single_modifier);

			if (!is_object($compiler->getSmarty()->security_policy)
				|| $compiler->getSmarty()->security_policy->isTrustedModifier($modifier, $compiler)
			) {

				if ($handler = $compiler->getModifierCompiler($modifier)) {
					$output = $handler->compile($single_modifier, $compiler);

				} elseif ($compiler->getSmarty()->getModifierCallback($modifier)) {
					$output = sprintf(
							'$_smarty_tpl->getSmarty()->getModifierCallback(%s)(%s)',
							var_export($modifier, true),
							$params
						);
				} elseif ($callback = $compiler->getPluginFromDefaultHandler($modifier, \Smarty\Smarty::PLUGIN_MODIFIERCOMPILER)) {
					$output = (new \Smarty\Compile\Modifier\BCPluginWrapper($callback))->compile($single_modifier, $compiler);
				} elseif ($function = $compiler->getPluginFromDefaultHandler($modifier, \Smarty\Smarty::PLUGIN_MODIFIER)) {
					if (!is_array($function)) {
						$output = "{$function}({$params})";
					} else {
						$operator = is_object($function[0]) ? '->' : '::';
						$output =  $function[0] . $operator . $function[1] . '(' . $params . ')';
					}
				}  else {
					$compiler->trigger_template_error("unknown modifier '{$modifier}'", null, true);
				}
			}
		}
		return $output;
	}
}