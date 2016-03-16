import React from 'react';
import { connect } from 'react-redux';
import { fetchSpeakerVideos } from '../../actions';
import DateGroupedVideoList from '../views/DateGroupedVideoList';
import RouterLink from '../containers/RouterLink';

class SpeakerDetail extends React.Component {

	componentDidMount () {
		const {speaker} = this.props.videos;

		if(!speaker || speaker.id != this.props.params.id) {
			this.props.fetchVideos();	
		}
	}

	componentWillReceiveProps(nextProps) {
		if(nextProps.params.id !== this.props.params.id) {
			this.props.fetchVideos();
		}
	}

	render () {
		if(this.props.loading) {
			return <h2>loading...</h2>
		}
		return (
			<div>
				{this.props.speaker &&
				<div>
					<h2>{this.props.speaker.name}</h2>
					<h4><RouterLink link='speakers'>All speakers</RouterLink></h4>
				</div>
				}
				<DateGroupedVideoList videos={this.props.videos} />
			</div>
		);
	}
}

export default connect (
	(state, ownProps) => {
		const {speakerVideos} = state.videos;
		return {
			speaker: speakerVideos.speaker,
			videos: speakerVideos.results,
			loading: speakerVideos.loading
		}
	},
	(dispatch, ownProps) => {
		return {
			fetchVideos () {
				dispatch(fetchSpeakerVideos(ownProps.params.id));
			}
		}
	}
)(SpeakerDetail);