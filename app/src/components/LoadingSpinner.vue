<template>
  <div :class="{ 'loading-spinner--absolute': absolute }" class="loading-spinner">
    <b-spinner variant="primary" label="Loading..."></b-spinner>
  </div>
</template>

<style lang="scss" scoped>
  .loading-spinner {
    text-align: center;
    opacity: 0;
    animation: delay-opacity ease-in 1.2s forwards;
    animation-delay: 0.2s;

    &--absolute {
      position: absolute;
      left: 50%;
      transform: translate(-50%, 150%);
    }
  }

  @keyframes delay-opacity {
    to {
      opacity: 1;
    }
  }
</style>

<script>
export default {
  name: 'LoadingSpinner',
  props: {
    absolute: {
      type: Boolean,
      required: false,
      default: false
    }
  }
}
</script>
