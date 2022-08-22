<div class="aside-nav">

    {{-- button for collapsing aside nav --}}
    <nav-slide-button icon-class="accordian-left-icon"></nav-slide-button>

    <ul>
        @if (request()->route()->getName() != 'admin.configuration.index')
            <?php $keys = explode('.', $menu->currentKey);  ?>
            @if(isset($keys) && strlen($keys[0]))
            @foreach (\Illuminate\Support\Arr::get($menu->items, current($keys) . '.children') as $item)

            @if (!core()->getConfigData('bulkupload.settings.general.status') && $item['key'] == 'catalog.bulkupload')
                <?php continue; ?>
            @endif

                <li class="{{ $menu->getActive($item) }}">
                    <a href="{{ $item['url'] }}">
                        {{ trans($item['name']) }}

                        @if ($menu->getActive($item))
                            <i class="angle-right-icon"></i>
                        @endif
                    </a>
                </li>
            @endforeach
            @endif
        @else
            @foreach ($config->items as $key => $item)
                <li class="{{ $item['key'] == request()->route('slug') ? 'active' : '' }}">
                    <a href="{{ route('admin.configuration.index', $item['key']) }}">
                        {{ isset($item['name']) ? trans($item['name']) : '' }}

                        @if ($item['key'] == request()->route('slug'))
                            <i class="angle-right-icon"></i>
                        @endif
                    </a>
                </li>
            @endforeach
        @endif
    </ul>
</div>
