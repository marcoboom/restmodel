<?php

namespace Restmodel\Contracts;

use Restmodel\Builder;

interface Paginate
{
    public function setPagination(Builder $builder, $perPage, $currentPage);

    public function getTotal(Builder $builder);
}
