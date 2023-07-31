#!/usr/bin/env bash

set -e

icewm-session&
cypress open ${@}
