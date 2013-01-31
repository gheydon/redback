<?php

namespace RocketSoftware\u2\RedBack;

interface uAssocArraySource {
  public function fieldExists($field);
  public function get($delta);
}