import { throwError } from '../actions';

const GENERIC_ERROR = "There seems to have been a problem.";

const xhrs = {};

export const cancel = (key) => {
	if(xhrs[key]) {
		xhrs[key].abort();
		delete xhrs[key];
	}
}

export const schedule = (key, req) => {
	xhrs[key] = req;
};

export const responseHandler = (dispatch, success, errorHandler) => {
    return (err, res) => {
        if (err || !res.ok) {
        	if(errorHandler) {
				errorHandler(err, res);
        	}
        	else {
				dispatch(throwError(GENERIC_ERROR));
        	}
        }
        else if(typeof success === 'function') {
        	success(res.body);
        }
    };	
};




// function ajax(method, url, data = null) {
//   return new Promise(function (resolve, reject) {
//     var req = request;
//     method = method.toUpperCase();

//     if (method !== 'GET') {
//       req = req[method.toLowerCase()].send(data);      
//     } 
//     else {
//       req = req.get(url);
//     }

//     req.end(function (err, res) {
//       if (res.ok) {      	
//         resolve(res.text);
//       } else {
//         reject(res.text);
//       }
//     });
//   });
// }

// export default ajax;