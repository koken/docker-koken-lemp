#!/bin/sh
sudo rm -rf www/*
sudo rm -rf mysql/*
docker build --rm -t r0b2g1t/koken:latest .
