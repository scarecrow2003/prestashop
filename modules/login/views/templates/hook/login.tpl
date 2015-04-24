<!-- Block login -->
<div id="login_block_home" class="block">
  <h4>Welcome!</h4>
  <div class="block_content">
    <p>Hello,
       {if isset($qq_id) && $qq_id}
           {$qq_id}
       {else}
           World
       {/if}
       !       
    </p>   
    <ul>
      <li><a href="{$my_module_link}" title="Click this link">Click me!</a></li>
    </ul>
  </div>
</div>
<!-- /Block login -->